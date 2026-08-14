<?php 
$title = "Tools Directory"; 
include __DIR__ . '/partials/header.php'; 
?>
    <style>

        .header {
            text-align: center;
            margin-bottom: 3rem;
            margin-top: 2rem;
        }

        .header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            letter-spacing: -0.025em;
        }

        .header p {
            color: var(--text-muted);
            font-size: 1.125rem;
            max-width: 600px;
            margin: 0 auto;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .controls {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            margin-bottom: 3rem;
            background-color: var(--surface-color);
            padding: 1.5rem;
            border-radius: 1rem;
            border: 1px solid var(--border-color);
        }

        @media (min-width: 768px) {
            .controls {
                flex-direction: row;
                align-items: center;
                justify-content: space-between;
            }
        }

        .search-box {
            position: relative;
            flex-grow: 1;
            max-width: 500px;
        }

        .search-box input {
            width: 100%;
            background-color: var(--bg-color);
            border: 1px solid var(--border-color);
            color: var(--text-main);
            padding: 0.75rem 1rem 0.75rem 2.5rem;
            border-radius: 0.5rem;
            font-size: 1rem;
            font-family: inherit;
            outline: none;
            transition: border-color 0.2s;
        }

        .search-box input:focus {
            border-color: var(--primary-color);
        }

        .search-box svg {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            width: 1.25rem;
            height: 1.25rem;
            color: var(--text-muted);
        }

        .categories {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .category-btn {
            background-color: var(--bg-color);
            border: 1px solid var(--border-color);
            color: var(--text-muted);
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .category-btn:hover {
            border-color: var(--text-main);
            color: var(--text-main);
        }

        .category-btn.active {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: var(--primary-text);
        }

        .tools-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .tool-card {
            background-color: var(--surface-color);
            border: 1px solid var(--border-color);
            border-radius: 1rem;
            padding: 1.5rem;
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
            transition: all 0.2s ease;
        }

        .tool-card:hover {
            transform: translateY(-4px);
            border-color: var(--primary-color);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        .tool-icon {
            width: 3rem;
            height: 3rem;
            border-radius: 0.75rem;
            background-color: rgba(56, 189, 248, 0.1);
            color: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.25rem;
        }

        .tool-icon svg {
            width: 1.5rem;
            height: 1.5rem;
        }

        .tool-category {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .tool-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .tool-desc {
            color: var(--text-muted);
            font-size: 0.95rem;
            flex-grow: 1;
        }

        .no-results {
            text-align: center;
            padding: 3rem;
            color: var(--text-muted);
            grid-column: 1 / -1;
            display: none;
        }
    </style>
</head>
<body>

    <div class="header">
        <h1>Tools Directory</h1>
        <p>A collection of powerful utilities and micro-apps to supercharge your workflow.</p>
    </div>

    <div class="container">
        
        <?php
            // Extract unique categories
            $categories = [];
            if (!empty($tools)) {
                $categories = array_unique(array_column($tools, 'category'));
                sort($categories);
            }
        ?>

        <div class="controls">
            <div class="search-box">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <input type="text" id="searchInput" placeholder="Search tools...">
            </div>
            
            <div class="categories" id="categoryFilters">
                <button class="category-btn active" data-category="all">All</button>
                <?php foreach($categories as $category): ?>
                    <button class="category-btn" data-category="<?= htmlspecialchars($category) ?>">
                        <?= htmlspecialchars($category) ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="tools-grid" id="toolsGrid">
            <?php if (!empty($tools)): ?>
                <?php foreach($tools as $tool): ?>
                    <?php 
                        $isExternal = $tool['is_external'] ?? false;
                        $href = $isExternal ? $tool['slug'] : '/tools/' . $tool['slug'];
                        $target = $isExternal ? 'target="_blank" rel="noopener noreferrer"' : '';
                        $tags = implode(',', $tool['tags'] ?? []);
                    ?>
                    <a href="<?= htmlspecialchars($href) ?>" <?= $target ?> class="tool-card" data-category="<?= htmlspecialchars($tool['category']) ?>" data-tags="<?= htmlspecialchars(strtolower($tags)) ?>">
                        <div class="tool-icon">
                            <?= $tool['icon'] ?>
                        </div>
                        <div class="tool-category">
                            <?= htmlspecialchars($tool['category']) ?>
                            <?php if($isExternal): ?>
                                <i data-lucide="external-link" style="width: 12px; height: 12px; display: inline-block; margin-left: 4px; vertical-align: text-top;"></i>
                            <?php endif; ?>
                        </div>
                        <h3 class="tool-title"><?= htmlspecialchars($tool['name']) ?></h3>
                        <p class="tool-desc"><?= htmlspecialchars($tool['description']) ?></p>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <div class="no-results" id="noResults">
                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="margin: 0 auto 1rem auto; opacity: 0.5;">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <h2>No tools found</h2>
                <p>Try adjusting your search or category filter.</p>
            </div>
        </div>

    </div>

    <script>
        const searchInput = document.getElementById('searchInput');
        const categoryBtns = document.querySelectorAll('.category-btn');
        const toolCards = document.querySelectorAll('.tool-card');
        const noResults = document.getElementById('noResults');

        function filterTools() {
            const query = searchInput.value.toLowerCase();
            const activeCategory = document.querySelector('.category-btn.active').dataset.category;
            let visibleCount = 0;

            toolCards.forEach(card => {
                const title = card.querySelector('.tool-title').innerText.toLowerCase();
                const desc = card.querySelector('.tool-desc').innerText.toLowerCase();
                const category = card.dataset.category;
                const tags = (card.dataset.tags || '').toLowerCase();

                const matchesSearch = title.includes(query) || desc.includes(query) || tags.includes(query);
                const matchesCategory = activeCategory === 'all' || category === activeCategory;

                if (matchesSearch && matchesCategory) {
                    card.style.display = 'flex';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });

            if (visibleCount === 0) {
                noResults.style.display = 'block';
            } else {
                noResults.style.display = 'none';
            }
        }

        searchInput.addEventListener('input', (e) => {
            filterTools();
        });

        categoryBtns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                // Update active state
                categoryBtns.forEach(b => b.classList.remove('active'));
                e.target.classList.add('active');

                // Update filter
                filterTools();
            });
        });
    </script>
<?php include __DIR__ . '/partials/footer.php'; ?>
