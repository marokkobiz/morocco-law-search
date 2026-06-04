<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marokko Biz | Moroccan Law Search</title>
    <link rel="icon" href="/marokko-biz-icon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
      href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Fraunces:opsz,wght@9..144,600;9..144,700&display=swap"
      rel="stylesheet"
    >
    <script type="module" crossorigin src="/assets/app.js"></script>
    <link rel="stylesheet" crossorigin href="/assets/index.css">
    <style>
      html.force-workspace #root .landing-shell {
        opacity: 0;
        pointer-events: none;
      }
    </style>
  </head>
  <body>
    <div id="root"></div>
    <script>
      (() => {
        const shouldOpenWorkspace = window.location.pathname.replace(/\/+$/, '') === '/app';

        if (!shouldOpenWorkspace) {
          return;
        }

        document.documentElement.classList.add('force-workspace');

        const openWorkspace = () => {
          const button = [...document.querySelectorAll('button, a')]
            .find((item) => /open app|enter workspace/i.test(item.textContent || ''));

          if (button) {
            button.click();
            document.documentElement.classList.remove('force-workspace');
            return true;
          }

          return false;
        };

        let attempts = 0;
        const interval = window.setInterval(() => {
          attempts += 1;

          if (openWorkspace() || attempts > 40) {
            window.clearInterval(interval);
            document.documentElement.classList.remove('force-workspace');
          }
        }, 125);

        window.addEventListener('load', openWorkspace);
        new MutationObserver(openWorkspace).observe(document.getElementById('root'), {
          childList: true,
          subtree: true,
        });
      })();
    </script>
  </body>
</html>
