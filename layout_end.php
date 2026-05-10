  <?php
  // Layout end code here
  ?>
  </div> <!-- end content-container -->
</div> <!-- end main-content -->

<!-- Sticky viewport-bottom scrollbar for any .table-responsive table -->
<div id="sticky-scroll-bar" style="position:fixed;bottom:0;left:0;width:100%;overflow-x:auto;overflow-y:hidden;height:16px;z-index:1000;background:#f8f9fa;border-top:1px solid #dee2e6;display:none;">
  <div id="sticky-scroll-inner" style="height:1px;"></div>
</div>
<script>
(function() {
  var outer = document.querySelector('.table-responsive');
  var bar   = document.getElementById('sticky-scroll-bar');
  var inner = document.getElementById('sticky-scroll-inner');
  if (!outer || !bar || !inner) return;

  function syncWidth() {
    inner.style.width = outer.scrollWidth + 'px';
  }
  function isTableInView() {
    var rect = outer.getBoundingClientRect();
    return rect.bottom > window.innerHeight && rect.top < window.innerHeight;
  }
  function updateBar() {
    syncWidth();
    var show = isTableInView();
    bar.style.display = show ? 'block' : 'none';
    if (show) {
      var r = outer.getBoundingClientRect();
      bar.style.left  = r.left + 'px';
      bar.style.width = r.width + 'px';
    }
  }

  var ticking = false;
  bar.addEventListener('scroll', function() { outer.scrollLeft = bar.scrollLeft; });
  outer.addEventListener('scroll', function() { bar.scrollLeft = outer.scrollLeft; });
  window.addEventListener('scroll', function() {
    if (!ticking) { requestAnimationFrame(function() { updateBar(); ticking = false; }); ticking = true; }
  });
  window.addEventListener('resize', updateBar);
  updateBar();
})();
</script>

<script src="js/modern-ui.js?v=20260213"></script>
</body>
</html>
