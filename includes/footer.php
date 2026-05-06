</main>
            </div>
        </div>
    </div>
<script>
    const themeToggle = document.getElementById('themeToggle');
    const setTheme = (theme) => {
        document.documentElement.dataset.theme = theme;
        localStorage.setItem('smartprintTheme', theme);
        if (themeToggle) {
            themeToggle.textContent = theme === 'dark' ? 'Light mode' : 'Dark mode';
        }
    };

    const storedTheme = localStorage.getItem('smartprintTheme');
    const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    setTheme(storedTheme || (prefersDark ? 'dark' : 'light'));

    if (themeToggle) {
        themeToggle.addEventListener('click', () => {
            setTheme(document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark');
        });
    }
</script>
</body>
</html>
