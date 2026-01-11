document.addEventListener('DOMContentLoaded', () => {
    const grid = document.getElementById('pluginGrid');
    const sortSelect = document.getElementById('sortSelect');
    const filterBtns = document.querySelectorAll('.filter-btn');

    let currentCategory = 'all';
    let currentSort = 'installs'; // newest, az, installs

    // Theme Logic
    const themeToggle = document.getElementById('themeToggle');

    const sunIcon = `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>`;
    const moonIcon = `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>`;

    function setTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem('theme', theme);
        themeToggle.innerHTML = theme === 'dark' ? sunIcon : moonIcon;
    }

    // Initialize Theme
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme) {
        setTheme(savedTheme);
    } else {
        setTheme(defaultColorMode);
    }

    themeToggle.addEventListener('click', () => {
        const currentTheme = document.documentElement.getAttribute('data-theme');
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        setTheme(newTheme);
    });


    function renderPlugins() {
        grid.innerHTML = '';

        let filtered = plugins.filter(p => {
            if (currentCategory === 'all') return true;
            if (!p.author_bio || !p.author_bio.category) return false;
            const cats = p.author_bio.category.split(',').map(c => c.trim());
            return cats.includes(currentCategory);
        });

        filtered.sort((a, b) => {
            if (currentSort === 'newest') {
                const dateA = new Date(a.published_at);
                const dateB = new Date(b.published_at);
                return dateB - dateA;
            } else if (currentSort === 'az') {
                return a.name.localeCompare(b.name);
            } else if (currentSort === 'installs') {
                return b.total_installs - a.total_installs;
            }
        });

        filtered.forEach(plugin => {
            const card = document.createElement('div');
            card.className = 'plugin-card';

            const iconSrc = plugin.local_icon ? plugin.local_icon : plugin.icon_url;
            const screenshotSrc = plugin.local_screenshot ? plugin.local_screenshot : plugin.screenshot_url;

            card.innerHTML = `
                <div class="card-image">
                    <img src="${screenshotSrc}" alt="${plugin.name} Screenshot" loading="lazy">
                </div>
                <div class="card-content">
                    <div class="card-header">
                        <img src="${iconSrc}" alt="${plugin.name} Icon" class="plugin-icon" loading="lazy">
                        <h3 class="plugin-title">${plugin.name}</h3>
                    </div>
                    <div class="plugin-stats">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="opacity: 0.7;"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                        ${plugin.total_installs} installs
                    </div>
                    <div class="plugin-meta">
                        ${plugin.author_bio && plugin.author_bio.description ? plugin.author_bio.description.substring(0, 100) + (plugin.author_bio.description.length > 100 ? '...' : '') : ''}
                    </div>
                    <a href="https://usetrmnl.com/recipes/${plugin.id}" target="_blank" class="install-btn">
                        <img src="https://usetrmnl.com/images/brand/badges/light/show-it-on-trmnl/trmnl-badge-show-it-on-light.svg" alt="Show it on TRMNL">
                    </a>
                </div>
            `;
            grid.appendChild(card);
        });
    }

    // Sort Select
    sortSelect.addEventListener('change', (e) => {
        currentSort = e.target.value;
        renderPlugins();
    });

    // Filter Buttons
    filterBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            // Remove active class from all
            filterBtns.forEach(b => b.classList.remove('active'));
            // Add active to clicked
            btn.classList.add('active');

            currentCategory = btn.dataset.category;
            renderPlugins();
        });
    });

    // Initial Render
    renderPlugins();
});
