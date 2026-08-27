</main>

<footer class="bg-[#0d1f3c] border-t border-[#1e3e68] py-6 mt-auto text-slate-400 text-xs no-print">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col md:flex-row items-center justify-between gap-4">
        <div>
            <span class="font-serif font-bold text-slate-200">UNITED FIVE CONSTRUCTION, INC.</span>
            <span class="mx-2 text-slate-600">|</span>
            <span>NYC DOB GC License #625679</span>
            <span class="mx-2 text-slate-600">|</span>
            <span>1 World Trade Center, Suite 8500, New York, NY 10007</span>
        </div>
        <div class="text-slate-500 text-[11px]">
            Confidential · Private Client Pre-Assessment Four-Phase Qualification System · v5.0
        </div>
    </div>
</footer>

</div> <!-- Close layout offset wrapper -->

<!-- ══ MOBILE SIDEBAR DRAWER TOGGLE SCRIPT ══════════ -->
<script>
    (function() {
        var btn = document.getElementById('hamburger-btn');
        var drawer = document.getElementById('mobile-sidebar');
        var overlay = document.getElementById('sidebar-overlay');
        var closeBtn = document.getElementById('sidebar-close-btn');

        if (!btn || !drawer || !overlay) return;

        function openSidebar() {
            drawer.classList.remove('-translate-x-full');
            drawer.classList.add('translate-x-0');

            overlay.classList.remove('opacity-0', 'pointer-events-none');
            overlay.classList.add('opacity-100', 'pointer-events-auto');

            btn.setAttribute('aria-expanded', 'true');

            var bars = btn.querySelectorAll('.ham-bar');
            if (bars.length === 3) {
                bars[0].style.transform = 'translateY(7px) rotate(45deg)';
                bars[1].style.opacity = '0';
                bars[1].style.width = '0';
                bars[2].style.transform = 'translateY(-7px) rotate(-45deg)';
            }

            document.body.style.overflow = 'hidden';
            drawer.dataset.isOpen = 'true';
        }

        function closeSidebar() {
            drawer.classList.add('-translate-x-full');
            drawer.classList.remove('translate-x-0');

            overlay.classList.add('opacity-0', 'pointer-events-none');
            overlay.classList.remove('opacity-100', 'pointer-events-auto');

            btn.setAttribute('aria-expanded', 'false');

            var bars = btn.querySelectorAll('.ham-bar');
            if (bars.length === 3) {
                bars[0].style.transform = '';
                bars[1].style.opacity = '1';
                bars[1].style.width = '22px';
                bars[2].style.transform = '';
            }

            document.body.style.overflow = '';
            drawer.dataset.isOpen = 'false';
        }

        btn.addEventListener('click', function() {
            drawer.dataset.isOpen === 'true' ? closeSidebar() : openSidebar();
        });
        overlay.addEventListener('click', closeSidebar);
        if (closeBtn) closeBtn.addEventListener('click', closeSidebar);

        // Escape key closes
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeSidebar();
        });

        // Auto-close when resizing to desktop
        var mq = window.matchMedia('(min-width: 768px)');
        mq.addEventListener('change', function(e) {
            if (e.matches) closeSidebar();
        });
    })();
</script>

</body>
</html>
