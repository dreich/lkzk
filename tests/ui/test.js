const puppeteer = require('puppeteer');

(async () => {
  const browser = await puppeteer.launch({
    headless: false, // Браузер виден
    args: ['--start-maximized', '--auto-open-devtools-for-tabs'], // Развернуть на весь экран и открыть dev tools
    defaultViewport: null // Viewport по размеру окна
  });
  const page = await browser.newPage();
  await page.goto('http://lkzk/', { waitUntil: 'networkidle0' }); // Главная страница с ожиданием JS
  
  // Ввод логина
  await page.type('input[name="login"]', '#vorobev#');
  
  // Ввод пароля (пустой)
  await page.type('input[name="password"]', '');
  
  // Нажатие кнопки входа
  await page.click('button[type="submit"]');
  
  await page.waitForNavigation({ waitUntil: 'networkidle0', timeout: 0 });
  // await page.waitForLoadState('networkidle0'); // Ожидание окончания сетевой активности и JS
  
  console.log('Авторизация выполнена');
  
  
  // Нажатие на ссылку "Дисциплины"
  await page.click('text=Дисциплины');
  await page.waitForNavigation({ waitUntil: 'networkidle0', timeout: 0 });

  await page.evaluate(() => alert('Всё сделано!'));
  
  console.log('Переход на Дисциплины выполнен');
})();