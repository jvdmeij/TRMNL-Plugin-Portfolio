# TRMNL Plugins Portfolio

A simple PHP-based viewer for your TRMNL plugins. This project uses the TRMNL api to fetch plugins associated with a specific TRMNL user ID and displays them in a responsive grid layout with filtering and sorting capabilities. View a demo at [trmnlplugins.xyz](https://www.trmnlplugins.xyz).

<img width="1920" height="1440" alt="trmnl-portfolio" src="https://github.com/user-attachments/assets/da9dae43-4083-40e2-8ef7-b9327be24006" />


## Features

-   **Plugin Gallery**: View all your plugins with screenshots and stats.
-   **Filtering & Sorting**: Filter by category and sort by installs, newest, or name.
-   **Dark Mode**: Includes a toggleable dark mode that persists across sessions.
-   **Caching**: Caches API responses and images locally to reduce API calls and improve performance.

## Prerequisites

-   PHP 7.4 or higher
-   Write permissions for the `plugins/` directory (created automatically)

## Setup

1.  **Clone the repository**:

2.  **Configure Application**:
    Copy `config_example.php` to `config.php`:
    ```bash
    cp config_example.php config.php
    ```
    Open `config.php` and update the `$userId` and `$refreshPass` variables:
    ```php
    $userId = '2633'; // Replace with your TRMNL user ID
    $refreshPass = 'secure_password'; // Set a secure password for refreshing data
    ```

3.  **Run the application**:
    Open the index.php in your browser.

4.  **Refresh Data**:
    To fetch the latest data from the TRMNL API, visit the page with `refresh=true` and your password (from `config.php`):
    `index.php?refresh=true&pass=YOUR_SECURE_PASSWORD`
    This will download new plugin data and images.

## Configuration

-   **Debug Mode**: Set `$debug = true;` in `config.php` to enable logging to `debug.log`.
-   **Default Theme**: Change `$defaultColorMode` in `config.php` to `'light'` or `'dark'`.

## Automation & Cron Job

You can set up a cron job to automatically refresh the plugin data and images in the background. This ensures your viewer always displays relatively current information without manual intervention.

**Content Update Frequency:**
When the refresh trigger is called, the system employs smart caching to minimize API usage:

-   **Plugin Data (`data.json`)**: Updated if the local copy is older than **23 hours**.
-   **Images (Icons & Screenshots)**: Updated if the local copy is older than **~7 days**.

If the local files are newer than these thresholds, the refresh process skips downloading them.
