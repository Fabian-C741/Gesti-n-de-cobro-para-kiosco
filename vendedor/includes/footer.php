    </div>

    <!-- Mobile Menu Toggle -->
    <button class="menu-toggle" onclick="toggleSidebar()">
        <i class="bi bi-list"></i>
    </button>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('show');
        }

        // Cerrar sidebar al hacer click fuera en móvil
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const toggle = document.querySelector('.menu-toggle');
            const navBtn = document.querySelector('.navbar-top .btn-link');
            
            if (window.innerWidth <= 768) {
                if (!sidebar.contains(event.target) && 
                    event.target !== toggle && 
                    !toggle.contains(event.target) &&
                    event.target !== navBtn &&
                    !navBtn?.contains(event.target)) {
                    sidebar.classList.remove('show');
                }
            }
        });
    </script>
</body>
</html>
