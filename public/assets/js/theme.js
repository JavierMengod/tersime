(function () {
  "use strict";

  const sidebar        = document.querySelector('.sidebar');
  const sidebarToggles = document.querySelectorAll('#sidebarToggle, #sidebarToggleTop');
  const fullscreenButton     = document.getElementById('pantalla-completa');
  const exitFullscreenButton = document.getElementById('pantalla-completa-exit');

  /*
   |--------------------------------------------------------------------------
   | Sidebar
   |--------------------------------------------------------------------------
   */

  if (sidebar) {

    const collapseElements   = [...document.querySelectorAll('.sidebar .collapse')];
    const sidebarCollapseList = collapseElements.map(el =>
      new bootstrap.Collapse(el, { toggle: false })
    );

    const overlay = document.getElementById('sidebarOverlay');
    let overlayEnabled = true;

    function openSidebar() {
      document.body.classList.add('sidebar-toggled');
      overlayEnabled = false;
      setTimeout(() => { overlayEnabled = true; }, 400);
    }

    function closeSidebar() {
      document.body.classList.remove('sidebar-toggled');
      sidebarCollapseList.forEach(c => c.hide());
    }

    sidebarToggles.forEach(toggle => {
      toggle.addEventListener('click', e => {
        e.preventDefault();
        e.stopPropagation();
        document.body.classList.contains('sidebar-toggled') ? closeSidebar() : openSidebar();
      });
    });

    if (overlay) {
      overlay.addEventListener('click', () => { if (overlayEnabled) closeSidebar(); });
    }

    const closeBtn = document.getElementById('sidebarClose');
    if (closeBtn) closeBtn.addEventListener('click', closeSidebar);

    window.addEventListener('resize', () => {
      const vw = Math.max(document.documentElement.clientWidth || 0, window.innerWidth || 0);
      if (vw < 768) sidebarCollapseList.forEach(c => c.hide());
    });
  }

  /*
   |--------------------------------------------------------------------------
   | Fullscreen
   |--------------------------------------------------------------------------
   */

  function isFullscreenActive() {
    return !!(
      document.fullscreenElement       ||
      document.webkitFullscreenElement ||
      document.mozFullScreenElement    ||
      document.msFullscreenElement     ||
      (window.innerWidth >= screen.width && window.innerHeight >= screen.height)
    );
  }

  function updateFullscreenButtons() {
    const active = isFullscreenActive();
    if (fullscreenButton)     fullscreenButton.classList.toggle('d-none', active);
    if (exitFullscreenButton) exitFullscreenButton.classList.toggle('d-none', !active);
  }

  if (fullscreenButton) {
    fullscreenButton.addEventListener('click', () => {
      const el = document.documentElement;
      if (el.requestFullscreen)            el.requestFullscreen();
      else if (el.mozRequestFullScreen)    el.mozRequestFullScreen();
      else if (el.webkitRequestFullscreen) el.webkitRequestFullscreen();
      else if (el.msRequestFullscreen)     el.msRequestFullscreen();
    });
  }

  if (exitFullscreenButton) {
    exitFullscreenButton.addEventListener('click', () => {
      if (document.exitFullscreen)            document.exitFullscreen();
      else if (document.mozCancelFullScreen)  document.mozCancelFullScreen();
      else if (document.webkitExitFullscreen) document.webkitExitFullscreen();
      else if (document.msExitFullscreen)     document.msExitFullscreen();
    });
  }

  document.addEventListener('fullscreenchange',       updateFullscreenButtons);
  document.addEventListener('webkitfullscreenchange', updateFullscreenButtons);
  document.addEventListener('mozfullscreenchange',    updateFullscreenButtons);
  document.addEventListener('MSFullscreenChange',     updateFullscreenButtons);
  window.addEventListener('resize',                   updateFullscreenButtons);

  updateFullscreenButtons();

})();
