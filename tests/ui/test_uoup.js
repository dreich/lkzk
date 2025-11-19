const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const LOG_FILE = path.resolve(__dirname, 'log.txt');
const ERR_FILE = path.resolve(__dirname, 'errors.txt');
const TEST_USER = { login: 'vorobev', role: 'Администратор УОУП' };
const WAIT_AFTER_IDLE_MS = Number(process.env.WAIT_AFTER_IDLE_MS || 500);

const formatTimestamp = () => new Intl.DateTimeFormat('ru-RU', {
  timeZone: 'Europe/Moscow',
  year: 'numeric',
  month: '2-digit',
  day: '2-digit',
  hour: '2-digit',
  minute: '2-digit',
  second: '2-digit'
}).format(new Date());

const logMessage = (message) => {
  const line = `[${formatTimestamp()}] ${message}`;
  console.log(message);
  fs.appendFileSync(LOG_FILE, line + '\n');
};

const logError = (message) => {
  const line = `[${formatTimestamp()}] ${message}`;
  console.error(message);
  fs.appendFileSync(LOG_FILE, line + '\n');
  fs.appendFileSync(ERR_FILE, line + '\n');
};

(async () => {
  const browser = await chromium.launch({
    headless: false,
    args: ['--start-maximized']
  });

  const context = await browser.newContext({ viewport: null });
  const page = await context.newPage();

  const menuItems = [
    {
      name: 'Нагрузка',
      selector: 'a[href="#/uoup_nagruzka"]',
      afterNavigate: async () => {
        const rowSelector = '#DataTables_Table_nagruzka tbody tr';
        const row = await page.waitForSelector(rowSelector, { timeout: 5000 }).catch(() => null);
        if (row) {
          await row.click();
          await page.waitForLoadState('networkidle');
          await page.waitForTimeout(WAIT_AFTER_IDLE_MS);
          logMessage('Клик по строке второй таблицы');
          logMessage(`URL: ${await page.url()}`);
        } else {
              logMessage('Не удалось найти строку во второй таблице на странице "Нагрузка"');
        }
      }
    },
    { name: 'Отказ кафедр', selector: 'a[href="#/uoup_chairs_refused"]' },
    { name: 'Нагрузка на изменение', selector: 'a[href="#/uoup_nagruzka_to_change"]' },
    { name: 'Нагрузка без кафедры', selector: 'a[href="#/uoup_nagruzka_no_chair"]' },
    { name: 'Режим работы', selector: 'a[href="#/system_mode"]' }
  ];

  const consoleErrors = [];
  let hadConsoleErrors = false;
  page.on('console', (msg) => {
    if (msg.type() === 'error') {
      consoleErrors.push({ text: msg.text(), location: msg.location() });
    }
  });

  const flushConsoleErrors = (menuName) => {
    if (consoleErrors.length === 0) return;
    const details = consoleErrors
      .map((err) => `${err.text} (${err.location.url}:${err.location.lineNumber})`)
      .join('\n');
    const currentUrl = page.url();
    logError(`[${TEST_USER.role} ${TEST_USER.login}] Ошибка в пункте меню "${menuName}":\nURL: ${currentUrl}\n${details}`);
    hadConsoleErrors = true;
    consoleErrors.length = 0;
  };

  try {
    await page.goto('http://lkzk/', { waitUntil: 'networkidle' });

    await page.fill('input[name="login"]', TEST_USER.login);
    await page.fill('input[name="password"]', '');
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(WAIT_AFTER_IDLE_MS);
    logMessage(`URL: ${await page.url()}`);

    logMessage('-----------------------------');
    logMessage(`Тест роли ${TEST_USER.role} ${TEST_USER.login} начат`);
    logMessage('Авторизация выполнена');

    for (const item of menuItems) {
      consoleErrors.length = 0;
      logMessage(`Переход: ${item.name}`);
      await page.click(item.selector);
      await page.waitForLoadState('networkidle');
      await page.waitForTimeout(WAIT_AFTER_IDLE_MS);
      logMessage(`URL: ${await page.url()}`);

      if (typeof item.afterNavigate === 'function') {
        await item.afterNavigate();
      }

      flushConsoleErrors(item.name);
    }

    if (hadConsoleErrors) {
      logError(`[${TEST_USER.role} ${TEST_USER.login}] Тест завершён с ошибками`);
      process.exitCode = 1;
    }
    else
    {
      logMessage(`[${TEST_USER.role} ${TEST_USER.login}] Тест успешно пройден. Ошибок в консоли нет.`);
    }
  } catch (error) {
    logError(`[${TEST_USER.role} ${TEST_USER.login}] Тест не пройден: ${error.message}`);
    process.exitCode = 1;
  } finally {
    await browser.close();
  }
})();
