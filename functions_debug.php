<?php
// functions_debug.php

// 1) Autoloader
require_once __DIR__ . '/vendor/autoload.php';

// 2) App config & analytics
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/analytics.php';

// 3) Production functions (defines scrapeCharacterData)
require_once __DIR__ . '/functions.php';

/**
 * Debug-only wrapper with verbose logging
 */
function scrapeCharacterDataDebug($url)
{
    logError("=== DEBUG SCRAPE START: {$url} ===");

    // Call the production scraper
    $data = scrapeCharacterData($url);

    // Fetch raw HTML for inspection
    try {
        $client  = new \Goutte\Client();
        $crawler = $client->request('GET', $url);
        $html    = $crawler->html();
    } catch (\Exception $e) {
        $html = '';
        logError("ERROR fetching HTML for debug: " . $e->getMessage());
    }

    // Log a snippet of the HTML
    logError("HTML snippet (first 2000 chars):\n" . substr($html, 0, 2000));

    // Count stat-blocks
    try {
        $count = $crawler->filter('div.stat-block')->count();
    } catch (\Exception $e) {
        $count = 0;
        logError("ERROR counting stat-blocks: " . $e->getMessage());
    }
    logError("Found div.stat-block count: {$count}");

    // Log first 20 text nodes
    $texts = [];
    try {
        $texts = $crawler
            ->filterXPath('//text()')
            ->each(fn($n) => trim($n->text()));
    } catch (\Exception $e) {
        logError("ERROR extracting text nodes: " . $e->getMessage());
    }
    $texts = array_values(array_filter($texts));
    logError("First 20 non-empty text nodes:\n" . implode(" | ", array_slice($texts, 0, 20)));

    // Log the scraped data
    logError(sprintf(
        "Raw scrape result → level=%d, resets=%d, location='%s', status='%s'",
        $data['level'],
        $data['resets'],
        $data['location'],
        $data['status']
    ));

    if ($data['level'] === 0) {
        logError("WARNING: Level is still 0 after scraping.");
    }
    if ($data['resets'] === 0) {
        logError("WARNING: Resets is still 0 after scraping.");
    }

    logError("=== DEBUG SCRAPE END ===");
    return $data;
}

/**
 * Invoked by debug_test.php
 */
function testCharacterScrapingDebug($url)
{
    return scrapeCharacterDataDebug($url);
}
