(function () {
  "use strict";

  var sidebar = document.querySelector('.sidebar');
  var sidebarToggles = document.querySelectorAll('#sidebarToggle, #sidebarToggleTop');

  var fullscreenButton = document.getElementById('pantalla-completa');
  var exitFullscreenButton = document.getElementById('pantalla-completa-exit');

  if (exitFullscreenButton) {
    exitFullscreenButton.style.display = 'none';
  }

  /*
   |--------------------------------------------------------------------------
   | Sidebar
   |--------------------------------------------------------------------------
   */

  if (sidebar) {

    var collapseElementList = [].slice.call(
      document.querySelectorAll('.sidebar .collapse')
    );

    var sidebarCollapseList = collapseElementList.map(function (collapseEl) {
      return new bootstrap.Collapse(collapseEl, {
        toggle: false
      });
    });

    var overlay = document.getElementById('sidebarOverlay');
    var overlayEnabled = true;

    function openSidebar() {
      document.body.classList.add('sidebar-toggled');
      overlayEnabled = false;
      setTimeout(function () { overlayEnabled = true; }, 400);
    }

    function closeSidebar() {
      document.body.classList.remove('sidebar-toggled');
      for (var bsCollapse of sidebarCollapseList) {
        bsCollapse.hide();
      }
    }

    for (var toggle of sidebarToggles) {
      toggle.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();

        if (document.body.classList.contains('sidebar-toggled')) {
          closeSidebar();
        } else {
          openSidebar();
        }
      });
    }

    if (overlay) {
      overlay.addEventListener('click', function () {
        if (!overlayEnabled) return;
        closeSidebar();
      });
    }

    var closeBtn = document.getElementById('sidebarClose');
    if (closeBtn) {
      closeBtn.addEventListener('click', function () {
        closeSidebar();
      });
    }

    window.addEventListener('resize', function () {
      var vw = Math.max(
        document.documentElement.clientWidth || 0,
        window.innerWidth || 0
      );
      if (vw < 768) {
        for (var bsCollapse of sidebarCollapseList) {
          bsCollapse.hide();
        }
      }
    });
  }

  /*
   |--------------------------------------------------------------------------
   | Fullscreen
   |--------------------------------------------------------------------------
   */

  function isFullscreenActive() {
    return !!(
      document.fullscreenElement ||
      document.webkitFullscreenElement ||
      document.mozFullScreenElement ||
      document.msFullscreenElement ||
      (window.innerWidth >= screen.width && window.innerHeight >= screen.height)
    );
  }

  function updateFullscreenButtons() {
    var active = isFullscreenActive();

    if (fullscreenButton) {
      fullscreenButton.style.display = active ? 'none' : 'inline-flex';
    }
    if (exitFullscreenButton) {
      exitFullscreenButton.style.display = active ? 'inline-flex' : 'none';
    }
  }

  if (fullscreenButton) {
    fullscreenButton.addEventListener('click', function () {
      var el = document.documentElement;
      if (el.requestFullscreen)       el.requestFullscreen();
      else if (el.mozRequestFullScreen)    el.mozRequestFullScreen();
      else if (el.webkitRequestFullscreen) el.webkitRequestFullscreen();
      else if (el.msRequestFullscreen)     el.msRequestFullscreen();
    });
  }

  if (exitFullscreenButton) {
    exitFullscreenButton.addEventListener('click', function () {
      if (document.exitFullscreen)           document.exitFullscreen();
      else if (document.mozCancelFullScreen)   document.mozCancelFullScreen();
      else if (document.webkitExitFullscreen)  document.webkitExitFullscreen();
      else if (document.msExitFullscreen)      document.msExitFullscreen();
    });
  }

  document.addEventListener('fullscreenchange',       updateFullscreenButtons);
  document.addEventListener('webkitfullscreenchange', updateFullscreenButtons);
  document.addEventListener('mozfullscreenchange',    updateFullscreenButtons);
  document.addEventListener('MSFullscreenChange',     updateFullscreenButtons);
  window.addEventListener('resize',                   updateFullscreenButtons);

  updateFullscreenButtons();

})();
