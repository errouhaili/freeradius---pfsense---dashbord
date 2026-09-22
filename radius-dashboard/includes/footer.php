<?php if (!empty($_SESSION['admin_id'])): ?>
</main>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('sidebarToggle')?.addEventListener('click', function(){
  document.getElementById('appSidebar').classList.toggle('show');
});
</script>
<?php endif; ?>
</body>
</html>
