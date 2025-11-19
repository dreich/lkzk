const { spawn } = require('child_process');
const fs = require('fs');
const path = require('path');

const testsDir = path.join(__dirname, 'ui');
const projectRoot = path.join(__dirname, '..');
const errorsFile = path.join(testsDir, 'errors.txt');
const logFile = path.join(testsDir, 'log.txt');
const solutionsFile = path.join(testsDir, 'solutions.md');
const appJsFile = path.join(projectRoot, 'app.js.php');
// время ожидания после загрузки страницы и отсутствия сетевой активности (чтобы в консоли успели появиться сообщения)
const WAIT_AFTER_IDLE_MS = 100;
const PLAYWRIGHT_HEADLESS = true; // переключайте вручную при необходимости

const testFiles = fs.readdirSync(testsDir)
  .filter((file) => file.endsWith('.js'))
  .map((file) => path.join(testsDir, file));

const readErrors = () => {
  if (!fs.existsSync(errorsFile)) return [];
  return fs.readFileSync(errorsFile, 'utf-8').trim().split(/\r?\n/);
};

const analyzeErrorBlock = (lines, context = {}) => {
  const joined = lines.join('\n');
  if (/Cannot read properties of null/.test(joined)) {
    const hint = context.menu === 'Нагрузка'
      ? 'Убедитесь, что resolve для маршрута "Нагрузка" возвращает данные (ajax/get/nagruzka_*.php) и что контроллер обрабатывает пустые ответы перед обращением к response.data.'
      : 'Проверьте, что асинхронный resolve/ответ сервера не возвращает null и добавьте защиту перед обращением к полям.';
    return hint;
  }
  if (/network/.test(joined)) {
    return 'Ошибка сети: убедитесь, что API доступен, авторизация не истекла и нет таймаутов/блокировок прокси.';
  }
  return 'Требуется ручной анализ: изучите стек, ответы API и состояние контроллера.';
};

const readLogs = () => {
  if (!fs.existsSync(logFile)) return [];
  const content = fs.readFileSync(logFile, 'utf-8').trim();
  if (!content) return [];
  return content.split(/\r?\n/);
};

let cachedLogLines = null;
const getLogLines = () => {
  if (cachedLogLines === null) {
    cachedLogLines = readLogs();
  }
  return cachedLogLines;
};

let cachedAppJsOffset = null;
const getAppJsPhpOffset = () => {
  if (cachedAppJsOffset !== null) return cachedAppJsOffset;
  if (!fs.existsSync(appJsFile)) {
    cachedAppJsOffset = 0;
    return cachedAppJsOffset;
  }
  const lines = fs.readFileSync(appJsFile, 'utf-8').split(/\r?\n/);
  let offset = 0;
  let inBlock = false;
  for (const line of lines) {
    if (!inBlock) {
      if (line.includes('<?php')) {
        inBlock = true;
        offset += 1;
        continue;
      }
    } else {
      offset += 1;
      if (line.includes('?>')) break;
      continue;
    }
    if (inBlock) {
      offset += 1;
    }
  }
  cachedAppJsOffset = offset;
  return cachedAppJsOffset;
};

const getStepsFromLogs = (testFile, headerLine, timestamp, menu) => {
  const logs = getLogLines();
  let tsIdx = logs.findIndex((line) => line.includes(headerLine));
  if (tsIdx === -1 && timestamp) {
    tsIdx = logs.findIndex((line) => line.includes(timestamp));
  }
  if (tsIdx === -1) return 'Логи не найдены.';

  let start = tsIdx;
  for (let i = tsIdx; i >= 0; i--) {
    const line = logs[i];
    if (line.includes(`Переход: ${menu}`)) {
      start = i;
      break;
    }
    if (/Переход: /.test(line)) {
      start = i;
      break;
    }
    if (line.includes('Тест роли')) {
      start = i;
      break;
    }
  }

  const steps = [];
  for (let i = start; i <= tsIdx; i++) {
    const line = logs[i];
    const normalized = line.toLowerCase();
    const clean = line.replace(/^[^\]]+\]\s+/, '');
    if (normalized.includes('переход:') || normalized.includes('клик') || normalized.includes('авторизация')) {
      steps.push(clean);
    }
    if (i === tsIdx) break;
    if (/Переход: /.test(line) && i > start && line.includes('Переход:') && !line.includes(menu)) break;
  }

  return steps.length ? steps.join('; ') : 'Нет действий в логах.';
};

const extractUrlAndController = (block) => {
  let url = '';
  let controller = '';
  block.forEach((line) => {
    const trimmed = line.trim();
    if (!trimmed) return;
    if (trimmed.includes('URL:')) {
      url = trimmed.split('URL:')[1].trim();
    }
    if (trimmed.includes('Клик по строке') && !url) {
      // fallback
      url = 'см. логи';
    }
    const ctrlMatch = trimmed.match(/at new <anonymous> \(([^:]+):\d+/);
    if (ctrlMatch) {
      controller = path.basename(ctrlMatch[1]);
    }
  });
  return { url, controller };
};

const findUrlBeforeLogLine = (headerLine, timestamp) => {
  const logs = getLogLines();
  let idx = logs.findIndex((line) => line.includes(headerLine));
  if (idx === -1 && timestamp) {
    idx = logs.findIndex((line) => line.includes(timestamp));
  }
  if (idx === -1) return '';
  for (let i = idx - 1; i >= 0; i--) {
    const line = logs[i];
    if (line.includes('URL:')) {
      return line.split('URL:')[1].trim();
    }
    if (/Переход: /.test(line) || line.includes('Тест роли') || line.includes('Авторизация')) {
      break;
    }
  }
  return '';
};

const extractErrorText = (block) => {
  for (const line of block) {
    if (!line) continue;
    const trimmed = line.trim();
    if (!trimmed) continue;
    if (trimmed.startsWith('[') || trimmed.startsWith('URL:') || trimmed.startsWith('at ') || /^https?:\/\//.test(trimmed)) {
      continue;
    }
    return trimmed;
  }
  return '';
};

(async () => {
  let previousErrors = readErrors();
  const perTestErrors = [];

  for (const file of testFiles) {
    console.log(`\n==== Запуск ${path.basename(file)} ====`);

    await new Promise((resolve) => {
      const child = spawn('node', [file], {
        stdio: 'inherit',
        env: {
          ...process.env,
          WAIT_AFTER_IDLE_MS: String(WAIT_AFTER_IDLE_MS),
          PLAYWRIGHT_HEADLESS: String(PLAYWRIGHT_HEADLESS)
        }
      });

      child.on('close', (code) => {
        if (code !== 0) {
          console.error(`Тест ${path.basename(file)} завершился с кодом ${code}`);
        } else {
          console.log(`Тест ${path.basename(file)} завершился успешно`);
        }
        resolve();
      });
    });

    const currentErrors = readErrors();
    const newLines = currentErrors.slice(previousErrors.length);
    if (newLines.length > 0) {
      perTestErrors.push({ testFile: file, lines: newLines });
    }
    previousErrors = currentErrors;
  }

  const blockify = (lines) => {
    const blocks = [];
    let current = [];
    for (const line of lines) {
      if (/^\[/.test(line) && line.includes('Ошибка в пункте меню')) {
        if (current.length > 0) blocks.push(current);
        current = [line];
      } else {
        current.push(line);
      }
    }
    if (current.length > 0) blocks.push(current);
    return blocks;
  };

  const parseHeader = (line) => {
    const match = line.match(/\[(.+?)\]\s+\[([^\]]+)\]\s+Ошибка в пункте меню "([^"]+)"/);
    if (!match) return { timestamp: '', actor: '', menu: '' };
    return { timestamp: match[1], actor: match[2], menu: match[3] };
  };

  const parseStackInfo = (block) => {
    for (const entry of block) {
      const match = entry.match(/http:\/\/[^\/]+\/([^:]+):(\d+):(\d+)/);
      if (match) {
        const resource = match[1].split('?')[0];
        let lineNum = Number(match[2]);
        const rel = resource.replace(/\//g, path.sep);
        const variants = [rel, rel.endsWith('.php') ? rel : `${rel}.php`];
        const resolved = variants.map((v) => path.join(projectRoot, v)).find((p) => fs.existsSync(p))
          || path.join(projectRoot, rel);
        if (resolved.endsWith(`${path.sep}app.js.php`)) {
          const offset = getAppJsPhpOffset();
          lineNum = Math.max(1, lineNum - offset);
        }
        return { fullPath: resolved, line: lineNum };
      }
    }
    return null;
  };

  const suggestions = [];

  perTestErrors.forEach(({ testFile, lines }) => {
    const blocks = blockify(lines);
    blocks.forEach((block) => {
      const header = block[0] || '[Неизвестная ошибка]';
      const { timestamp, actor, menu } = parseHeader(header);
      const stackInfo = parseStackInfo(block);
      const relTest = path.relative(projectRoot, testFile).replace(/\\/g, '/');
      let fileRef = 'файл не определён';
      if (stackInfo) {
        const relPathDisplay = path.relative(projectRoot, stackInfo.fullPath).replace(/\\/g, '/');
        const relPathLink = path.relative(testsDir, stackInfo.fullPath).replace(/\\/g, '/');
        const linkTarget = relPathLink.startsWith('.') ? relPathLink : `./${relPathLink}`;
        const label = `${relPathDisplay}:${stackInfo.line}`;
        fileRef = `[${label}](${linkTarget}#L${stackInfo.line})`;
      }
      const reproduction = getStepsFromLogs(testFile, header, timestamp, menu);
      let { url, controller } = extractUrlAndController(block);
      if (!url) {
        url = findUrlBeforeLogLine(header, timestamp) || 'не указан';
      }
      const errorText = extractErrorText(block);
      const displayError = errorText || 'не извлечена';
      const controllerLine = controller ? `- **Контроллер:** ${controller}\n` : '';
      suggestions.push(
        `### ${header}\n- **Тест:** [${relTest}](./${relTest})\n- **Файл:** ${fileRef}\n- **URL:** ${url}\n${controllerLine}- **Ошибка:** ${displayError}\n- **Путь:** меню "${menu || 'неизвестно'}"\n- **Шаги:** ${reproduction}\n- **Решение:** ${analyzeErrorBlock(block, { menu })}\n`
      );
    });
  });

  if (suggestions.length > 0) {
    fs.writeFileSync(solutionsFile, suggestions.join('\n'));
    console.log(`Создан файл рекомендаций: ${solutionsFile}`);
  } else {
    fs.writeFileSync(solutionsFile, 'Ошибок не обнаружено.');
    console.log('Ошибок не обнаружено, solutions.txt содержит соответствующее сообщение.');
  }
})();
