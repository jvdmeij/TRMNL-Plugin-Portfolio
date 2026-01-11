<?php

// Load Configuration
if (!file_exists(__DIR__ . '/config.php')) {
    die("Error: config.php not found. Please create one based on config_example.php");
}
require_once __DIR__ . '/config.php';

function logMessage($message)
{
    global $debug;
    if ($debug) {
        $logFile = __DIR__ . '/debug.log';
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($logFile, "[$timestamp] $message" . PHP_EOL, FILE_APPEND);
    }
}

$baseUrl = "https://usetrmnl.com/recipes.json?search=&sort-by=name&user_id={$userId}";
$pluginsDir = __DIR__ . '/plugins';

// Ensure plugins directory exists
if (!is_dir($pluginsDir)) {
    mkdir($pluginsDir, 0755, true);
}

// Function to fetch all pages
function fetchAllPlugins($url)
{
    $allPlugins = [];
    $nextPage = $url;

    $options = [
        "http" => [
            "header" => "User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36\r\n"
        ]
    ];
    $context = stream_context_create($options);

    while ($nextPage) {
        $json = @file_get_contents($nextPage, false, $context);
        if (!$json) {
            break;
        }

        $data = json_decode($json, true);
        if (!$data || !isset($data['data'])) {
            break;
        }

        $allPlugins = array_merge($allPlugins, $data['data']);

        // Handle next page URL - it might be relative or absolute
        if (isset($data['next_page_url']) && $data['next_page_url']) {
            if (strpos($data['next_page_url'], 'http') === 0) {
                $nextPage = $data['next_page_url'];
            } else {
                $nextPage = "https://usetrmnl.com" . $data['next_page_url'];
            }
        } else {
            $nextPage = null;
        }
    }
    return $allPlugins;
}

// Function to download image with age check
function downloadImage($url, $path, $maxAge = null)
{
    $shouldDownload = !file_exists($path);

    if (!$shouldDownload && $maxAge !== null) {
        if (time() - filemtime($path) > $maxAge) {
            $shouldDownload = true;
        }
    }

    if ($shouldDownload) {
        $content = @file_get_contents($url);
        if ($content) {
            file_put_contents($path, $content);
            return true;
        }
    }
    return false;
}

// Handle Refresh
if (isset($_GET['refresh']) && $_GET['refresh'] === 'true') {
    if (!$userId) {
        die("Error: User ID is not configured.");
    }
    if (!isset($_GET['pass']) || $_GET['pass'] !== $refreshPass) {
        die("Error: Invalid or missing refresh password.");
    }
    logMessage("Starting refresh process...");
    $plugins = fetchAllPlugins($baseUrl);
    logMessage("Fetched " . count($plugins) . " plugins from API.");


    foreach ($plugins as $plugin) {
        $id = $plugin['id'];
        $pluginDir = $pluginsDir . '/' . $id;

        if (!is_dir($pluginDir)) {
            if (!mkdir($pluginDir, 0755, true)) {
                logMessage("Failed to create directory: $pluginDir");
                continue;
            }
            logMessage("Created directory: $pluginDir");
        }


        // Save JSON - Update if older than a little less than 1 day (82800 seconds)
        $jsonPath = $pluginDir . '/data.json';
        $shouldUpdateJson = !file_exists($jsonPath) || (time() - filemtime($jsonPath) > 82800);

        if ($shouldUpdateJson) {
            file_put_contents($jsonPath, json_encode($plugin, JSON_PRETTY_PRINT));
            logMessage("Updated data.json for plugin: $id");
        } else {
            logMessage("Skipped data.json update for plugin: $id (not older than ~1 day)");
        }


        // Image Max Age: ~1 week (579600 seconds)
        $imageMaxAge = 579600;

        // Download Icon
        if (isset($plugin['icon_url']) && $plugin['icon_url']) {
            $ext = 'png'; // Default
            if (isset($plugin['icon_content_type'])) {
                if (strpos($plugin['icon_content_type'], 'svg') !== false)
                    $ext = 'svg';
                elseif (strpos($plugin['icon_content_type'], 'jpeg') !== false)
                    $ext = 'jpg';
                elseif (strpos($plugin['icon_content_type'], 'gif') !== false)
                    $ext = 'gif';
            }
            if (downloadImage($plugin['icon_url'], $pluginDir . '/icon.' . $ext, $imageMaxAge)) {
                logMessage("Downloaded icon for plugin: $id");
            }
        }


        // Download Screenshot
        if (isset($plugin['screenshot_url']) && $plugin['screenshot_url']) {
            if (downloadImage($plugin['screenshot_url'], $pluginDir . '/screenshot.png', $imageMaxAge)) {
                logMessage("Downloaded screenshot for plugin: $id");
            }
        }

    }

    // Redirect to remove refresh param
    logMessage("Refresh process completed. Redirecting...");
    header("Location: index.php");
    exit;

}

// Read Plugins from Disk
$loadedPlugins = [];
$categories = [];
$categoryCounts = [];

if (is_dir($pluginsDir)) {
    $dirs = scandir($pluginsDir);
    foreach ($dirs as $dir) {
        if ($dir === '.' || $dir === '..')
            continue;

        $jsonPath = $pluginsDir . '/' . $dir . '/data.json';
        if (file_exists($jsonPath)) {
            $pluginData = json_decode(file_get_contents($jsonPath), true);
            if ($pluginData) {
                // Add local paths for images
                $iconFiles = glob($pluginsDir . '/' . $dir . '/icon.*');
                $pluginData['local_icon'] = $iconFiles ? 'plugins/' . $dir . '/' . basename($iconFiles[0]) : null;

                $screenshotFiles = glob($pluginsDir . '/' . $dir . '/screenshot.*');
                $pluginData['local_screenshot'] = $screenshotFiles ? 'plugins/' . $dir . '/' . basename($screenshotFiles[0]) : null;

                // Calculate Total Installs
                $installs = isset($pluginData['stats']['installs']) ? (int) $pluginData['stats']['installs'] : 0;
                $forks = isset($pluginData['stats']['forks']) ? (int) $pluginData['stats']['forks'] : 0;
                $pluginData['total_installs'] = $installs + $forks;

                $loadedPlugins[] = $pluginData;

                // Extract Categories
                if (isset($pluginData['author_bio']['category'])) {
                    $cats = explode(',', $pluginData['author_bio']['category']);
                    foreach ($cats as $cat) {
                        $cat = trim($cat);
                        if ($cat) {
                            if (!in_array($cat, $categories)) {
                                $categories[] = $cat;
                            }
                            if (!isset($categoryCounts[$cat])) {
                                $categoryCounts[$cat] = 0;
                            }
                            $categoryCounts[$cat]++;
                        }
                    }
                }
            }
        }
    }
}

sort($categories);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($siteName); ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=EB+Garamond:ital,wght@0,400..800;1,400..800&family=Inter:wght@400;500;600;700&display=swap"
        rel="stylesheet">
</head>

<body>

    <header class="site-header">
        <div class="container" style="position: relative;">
            <button id="themeToggle" class="theme-toggle" aria-label="Toggle dark mode">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
                </svg>
            </button>
            <h1><?php echo htmlspecialchars($siteName); ?></h1>
            <p class="subtitle subtitle--small">Plugins for <a href="https://usetrmnl.com/" target="_blank">TRMNL</a> -
                an e-ink companion that helps you
                stay focused.</p>
            <p class="subtitle"><?php echo count($loadedPlugins); ?> plugins available</p>

            <div class="controls">
                <div class="filters">
                    <button class="filter-btn active" data-category="all">All</button>
                    <?php foreach ($categories as $category): ?>
                        <button class="filter-btn"
                            data-category="<?php echo htmlspecialchars($category); ?>"><?php echo htmlspecialchars(ucfirst($category)); ?>
                            <span class="filter-count">(<?php echo $categoryCounts[$category]; ?>)</span></button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        </div>
    </header>

    <main class="container">
        <?php if (!$userId): ?>
            <div class="warning-box">
                <h3>Configuration Required</h3>
                <p>Please open <code>config.php</code> and configure your <code>$userId</code>.</p>
                <p>You can find your User ID in any of your TRMNL plugin's variables under <code>trmnl.user.id</code>.</p>
            </div>
        <?php else: ?>
            <div class="sort-wrapper" style="margin-bottom: 20px; display: flex; justify-content: flex-end;">
                <select id="sortSelect" class="sort-select" style="max-width: 200px;">
                    <option value="installs" selected>Most Installs</option>
                    <option value="newest">Newest First</option>
                    <option value="az">A-Z</option>
                </select>
            </div>
            <div class="plugin-grid" id="pluginGrid">
                <!-- Plugins will be rendered here by JS -->
            </div>
        <?php endif; ?>
    </main>

    <script>
        const plugins = <?php echo json_encode($loadedPlugins); ?>;
        const defaultColorMode = "<?php echo $defaultColorMode; ?>";
    </script>
    <script src="js/script.js"></script>
</body>

</html>