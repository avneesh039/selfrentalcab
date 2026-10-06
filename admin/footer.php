</div> <!-- End of Page Work Area -->
</div> <!-- End of Flex Row -->

<script>
    // Sidebar toggle logic for mobile layouts
    const menuBtn = document.getElementById('sidebar-toggle-btn');
    const sidebar = document.getElementById('admin-sidebar');
    
    if (menuBtn && sidebar) {
        menuBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            sidebar.classList.toggle('-translate-x-full');
        });
        
        // Click outside side bar to close it on mobile
        document.body.addEventListener('click', () => {
            if (window.innerWidth < 1024) {
                sidebar.classList.add('-translate-x-full');
            }
        });
        
        sidebar.addEventListener('click', (e) => {
            e.stopPropagation();
        });
    }
</script>
</body>
</html>
