<?php
// functions.php (drop-in replacement)
// Purpose: Robust scraper using Guzzle HTTP client with fallback to cURL.
// Requirements: composer require guzzlehttp/guzzle symfony/dom-crawler

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/analytics.php';
require __DIR__ . '/vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

function scrapeCharacterData(string $url): array
{
    logDebug("Starting character data scraping", ['url' => $url]);

    // Try multiple user agents for better cross-browser compatibility
    $userAgents = [
        // Chrome (most common)
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        // Firefox
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:109.0) Gecko/20100101 Firefox/121.0',
        // Edge
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 Edg/120.0.0.0',
        // Safari
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.1 Safari/605.1.15',
        // Mobile Chrome
        'Mozilla/5.0 (Linux; Android 10; SM-G973F) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36',
        // Mobile Safari
        'Mozilla/5.0 (iPhone; CPU iPhone OS 17_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.1 Mobile/15E148 Safari/604.1'
    ];

    $html = null;
    $lastError = null;

    // Try with different user agents
    foreach ($userAgents as $index => $userAgent) {
        try {
            logDebug("Attempting scrape with user agent", ['agent_index' => $index, 'url' => $url]);
            $client = new Client([
                'timeout' => 30,
                'headers' => [
                    'User-Agent' => $userAgent,
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
                    'Accept-Language' => 'en-US,en;q=0.9,es;q=0.8,pt;q=0.7',
                    'Accept-Encoding' => 'gzip, deflate', // Removed 'br' (Brotli) as it causes issues with some servers
                    'Connection' => 'keep-alive',
                    'Upgrade-Insecure-Requests' => '1',
                    'Sec-Fetch-Dest' => 'document',
                    'Sec-Fetch-Mode' => 'navigate',
                    'Sec-Fetch-Site' => 'none',
                    'Sec-Fetch-User' => '?1',
                    'Cache-Control' => 'max-age=0',
                    'DNT' => '1',
                ],
                'verify' => false, // Disable SSL verification for testing
                'allow_redirects' => true,
                'http_errors' => false, // Don't throw exceptions on HTTP error codes
            ]);

            $response = $client->get($url);
            $html = $response->getBody()->getContents();

            // Check if we got valid HTML content
            if ($html && strlen($html) > 100 && (strpos($html, '<html') !== false || strpos($html, '<HTML') !== false || strpos($html, '<!DOCTYPE') !== false)) {
                error_log("[MU Tracker] Successfully fetched content with User-Agent: " . substr($userAgent, 0, 50) . "... (Length: " . strlen($html) . ")");
                break;
            } else {
                error_log("[MU Tracker] Invalid content received with User-Agent: " . substr($userAgent, 0, 50) . "... (Length: " . strlen($html) . ")");
                $html = null;
            }
        } catch (RequestException $e) {
            $lastError = $e->getMessage();
            error_log('[MU Tracker] HTTP request failed with ' . substr($userAgent, 0, 50) . '...: ' . $e->getMessage());
            continue;
        } catch (Exception $e) {
            $lastError = $e->getMessage();
            error_log('[MU Tracker] Request error with ' . substr($userAgent, 0, 50) . '...: ' . $e->getMessage());
            continue;
        }
    }

    // If all user agents failed, try cURL fallback
    if (!$html) {
        error_log('[MU Tracker] All User-Agent attempts failed, trying cURL fallback');
        $html = fetchWithCurl($url);
        if (!$html) {
            error_log('[MU Tracker] cURL fallback also failed');
            return [
                'name'         => 'Unknown',
                'status'       => 'Error',
                'level'        => 0,
                'resets'       => 0,
                'grand_resets' => 0,
                'class'        => 'Unknown',
                'gens'         => 'Unknown',
                'guild'        => 'Unknown',
                'location'     => 'Unknown',
            ];
        }
    }

    try {
        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new DOMXPath($dom);

        // Helper to extract integer from various patterns
        $getNumber = function (string $label) use ($xpath): int {
            // Try multiple patterns for finding the value
            $patterns = [
                "//label[normalize-space(text()) = '$label']/following-sibling::div",
                "//label[contains(text(), '$label')]/following-sibling::div",
                "//*[contains(text(), '$label')]/following-sibling::*",
                "//*[contains(text(), '$label')]/parent::*/following-sibling::*",
                "//*[contains(text(), '$label')]/parent::*/parent::*//*[contains(@class, 'value') or contains(@class, 'data')]",
                "//*[contains(text(), '$label')]/parent::*//*[contains(@class, 'value') or contains(@class, 'data')]",
                "//*[contains(text(), '$label')]/parent::*//*[not(contains(@class, 'label'))]",
                // Dragon MU specific patterns
                "//*[contains(text(), '$label')]/following-sibling::*[contains(text(), 'LVL') or contains(text(), 'Resets') or contains(text(), 'Grand Resets')]",
                "//*[contains(text(), '$label')]/parent::*/following-sibling::*[contains(text(), 'LVL') or contains(text(), 'Resets') or contains(text(), 'Grand Resets')]",
                "//*[contains(text(), '$label')]/parent::*/parent::*/following-sibling::*[contains(text(), 'LVL') or contains(text(), 'Resets') or contains(text(), 'Grand Resets')]",
            ];

            foreach ($patterns as $pattern) {
                $nodes = $xpath->query($pattern);
                if ($nodes->length) {
                    $text = trim($nodes->item(0)->textContent);
                    $number = (int) filter_var($text, FILTER_SANITIZE_NUMBER_INT);
                    if ($number > 0) {
                        return $number;
                    }
                }
            }
            return 0;
        };

        // Helper to extract text from various patterns
        $getText = function (string $label) use ($xpath): string {
            // Try multiple patterns for finding the value
            $patterns = [
                "//label[normalize-space(text()) = '$label']/following-sibling::div",
                "//label[contains(text(), '$label')]/following-sibling::div",
                "//*[contains(text(), '$label')]/following-sibling::*",
                "//*[contains(text(), '$label')]/parent::*/following-sibling::*",
                "//*[contains(text(), '$label')]/parent::*/parent::*//*[contains(@class, 'value') or contains(@class, 'data')]",
                "//*[contains(text(), '$label')]/parent::*//*[contains(@class, 'value') or contains(@class, 'data')]",
                "//*[contains(text(), '$label')]/parent::*//*[not(contains(@class, 'label'))]",
            ];

            foreach ($patterns as $pattern) {
                $nodes = $xpath->query($pattern);
                if ($nodes->length) {
                    $text = trim($nodes->item(0)->textContent);
                    if (!empty($text) && $text !== $label) {
                        return $text;
                    }
                }
            }
            return 'Unknown';
        };

        // Extract character name first
        $name = 'Unknown';

        // Try multiple patterns to find character name
        $namePatterns = [
            // Dragon MU specific patterns - more precise
            "//*[contains(text(), 'Name') and not(contains(text(), 'Top Players'))]/following-sibling::*[1]",
            "//*[normalize-space(text()) = 'Name']/following-sibling::*[1]",
            "//*[contains(text(), 'Character Information')]/following-sibling::*//*[contains(text(), 'Name')]/following-sibling::*[1]",
            "//*[contains(text(), 'Character Information')]/following-sibling::*//*[normalize-space(text()) = 'Name']/following-sibling::*[1]",
            // Generic patterns
            "//h1[contains(@class, 'character') or contains(@class, 'name') or contains(@class, 'title')]",
            "//h2[contains(@class, 'character') or contains(@class, 'name') or contains(@class, 'title')]",
            "//*[contains(@class, 'character-name') or contains(@class, 'player-name')]",
            "//title",
            "//*[contains(@class, 'profile')]//h1",
            "//*[contains(@class, 'profile')]//h2",
            "//*[contains(@class, 'char-info')]//h1",
            "//*[contains(@class, 'char-info')]//h2",
            "//*[contains(@class, 'player-info')]//h1",
            "//*[contains(@class, 'player-info')]//h2"
        ];

        foreach ($namePatterns as $pattern) {
            $nameNodes = $xpath->query($pattern);
            if ($nameNodes->length > 0) {
                $nameText = trim($nameNodes->item(0)->textContent);
                // Clean up the name - remove common suffixes and prefixes
                $nameText = preg_replace('/\s*-\s*.*$/', '', $nameText); // Remove everything after dash
                $nameText = preg_replace('/\s*\(.*\)$/', '', $nameText); // Remove parenthetical content
                $nameText = preg_replace('/\s*\[.*\]$/', '', $nameText); // Remove bracketed content
                $nameText = trim($nameText);

                // Skip common navigation/menu items
                $skipItems = ['Top Players', 'Top Guilds', 'Top Killers', 'Top Voters', 'Top Gens', 'Top BC', 'Top DS', 'Top CC', 'Home Page', 'Download', 'About Game', 'Community', 'Support', 'Free Rewards'];
                if (in_array($nameText, $skipItems)) {
                    continue;
                }

                if (!empty($nameText) && strlen($nameText) > 2 && strlen($nameText) < 50 && !preg_match('/^\d+$/', $nameText)) {
                    $name = $nameText;
                    error_log("[MU Tracker] Found character name: $name");
                    break;
                }
            }
        }

        // If name is still unknown, try to extract from URL path
        if ($name === 'Unknown') {
            $path = parse_url($url, PHP_URL_PATH);
            if ($path) {
                $pathSegments = explode('/', trim($path, '/'));
                $lastSegment = end($pathSegments);
                if ($lastSegment && $lastSegment !== 'X9999') {
                    $decodedName = urldecode($lastSegment);
                    // Convert hex encoded names (common in MU Online URLs)
                    if (ctype_xdigit($decodedName)) {
                        $name = hex2bin($decodedName);
                        if ($name !== false) {
                            error_log("[MU Tracker] Found character name from hex URL: $name");
                        } else {
                            $name = $decodedName;
                        }
                    } else {
                        $name = $decodedName;
                    }
                }
            }
        }

        // Extract core stats
        $level       = $getNumber('Level');
        $resets      = $getNumber('Resets');
        $grandResets = $getNumber('Grand Resets');
        $class       = $getText('Class');
        $gens        = $getText('Gens');
        $guild       = $getText('Guild');
        $location    = $getText('Location');

        error_log("[MU Tracker] Initial extraction: level=$level, resets=$resets, grand_resets=$grandResets, class=$class, gens=$gens, guild=$guild, location=$location");

        // Dragon MU specific extraction patterns
        if ($level === 0 || $resets === 0) {
            // Try to find level with "LVL" pattern
            $levelNodes = $xpath->query("//*[contains(text(), 'LVL')]");
            foreach ($levelNodes as $node) {
                $text = trim($node->textContent);
                if (preg_match('/(\d+)\s*LVL/', $text, $matches)) {
                    $level = (int)$matches[1];
                    error_log("[MU Tracker] Found level with LVL pattern: $level");
                    break;
                }
            }

            // Try to find resets - improved pattern for Dragon MU
            $resetNodes = $xpath->query("//*[contains(text(), 'Resets')]");
            foreach ($resetNodes as $node) {
                $text = trim($node->textContent);
                if (preg_match('/Resets\s*(\d+)/', $text, $matches)) {
                    $resets = (int)$matches[1];
                    error_log("[MU Tracker] Found resets: $resets");
                    break;
                }
            }

            // Try to find grand resets - improved pattern for Dragon MU
            $grandResetNodes = $xpath->query("//*[contains(text(), 'Grand Resets')]");
            foreach ($grandResetNodes as $node) {
                $text = trim($node->textContent);
                if (preg_match('/Grand Resets\s*(\d+)/', $text, $matches)) {
                    $grandResets = (int)$matches[1];
                    error_log("[MU Tracker] Found grand resets: $grandResets");
                    break;
                }
            }
        }

        // Dragon MU specific class extraction
        if ($class === 'Unknown') {
            $classNodes = $xpath->query("//*[contains(text(), 'Class')]");
            foreach ($classNodes as $node) {
                $text = trim($node->textContent);
                if (preg_match('/Class\s*(.+)/', $text, $matches)) {
                    $class = trim($matches[1]);
                    error_log("[MU Tracker] Found class: $class");
                    break;
                }
            }
        }

        // Dragon MU specific location extraction
        if ($location === 'Unknown') {
            $locationNodes = $xpath->query("//*[contains(text(), 'Location')]");
            foreach ($locationNodes as $node) {
                $text = trim($node->textContent);
                if (preg_match('/Location\s*(.+)/', $text, $matches)) {
                    $location = trim($matches[1]);
                    error_log("[MU Tracker] Found location: $location");
                    break;
                }
            }
        }

        // Dragon MU specific gens extraction
        if ($gens === 'Unknown') {
            $gensNodes = $xpath->query("//*[contains(text(), 'Gens')]");
            foreach ($gensNodes as $node) {
                $text = trim($node->textContent);
                if (preg_match('/Gens\s*(.+)/', $text, $matches)) {
                    $gens = trim($matches[1]);
                    error_log("[MU Tracker] Found gens: $gens");
                    break;
                }
            }
        }

        // Dragon MU specific guild extraction
        if ($guild === 'Unknown') {
            $guildNodes = $xpath->query("//*[contains(text(), 'Guild')]");
            foreach ($guildNodes as $node) {
                $text = trim($node->textContent);
                if (preg_match('/Guild\s*(.+)/', $text, $matches)) {
                    $guild = trim($matches[1]);
                    error_log("[MU Tracker] Found guild: $guild");
                    break;
                }
            }
        }

        // If we didn't find data with labels, try alternative approaches
        if ($level === 0 && $resets === 0) {
            error_log("[MU Tracker] No data found with labels, trying alternative approaches");

            // Try to find numbers in the page that might be level/resets
            $allText = $xpath->query("//text()[normalize-space(.) != '']");
            foreach ($allText as $textNode) {
                $text = trim($textNode->textContent);
                if (preg_match('/\b(\d{3,4})\b/', $text, $matches)) {
                    $number = (int)$matches[1];
                    if ($number >= 100 && $number <= 9999) {
                        // This could be a level
                        if ($level === 0) {
                            $level = $number;
                            error_log("[MU Tracker] Found potential level: $number in text: $text");
                        }
                    }
                }
            }

            // Try to find reset patterns
            $resetPatterns = [
                '//*[contains(text(), "reset") or contains(text(), "Reset")]',
                '//*[contains(text(), "resets") or contains(text(), "Resets")]',
            ];

            foreach ($resetPatterns as $pattern) {
                $nodes = $xpath->query($pattern);
                foreach ($nodes as $node) {
                    $text = $node->textContent;
                    if (preg_match('/\b(\d+)\b/', $text, $matches)) {
                        $number = (int)$matches[1];
                        if ($number > 0 && $number < 50000) {
                            $resets = $number;
                            error_log("[MU Tracker] Found potential resets: $number in text: $text");
                            break 2;
                        }
                    }
                }
            }
        }

        // --- Improved Online/Offline detection ---
        $status = 'Offline';

        // Debug logging
        error_log("[MU Tracker] Final scraped data: name=$name, level=$level, resets=$resets, grand_resets=$grandResets, class=$class, gens=$gens, guild=$guild, location=$location, status=$status");

        // Dragon MU specific status detection
        $statusNodes = $xpath->query("//*[contains(text(), 'Offline') or contains(text(), 'Online')]");
        foreach ($statusNodes as $node) {
            $text = trim($node->textContent);
            if (preg_match('/(Online|Offline)/', $text, $matches)) {
                $status = $matches[1];
                error_log("[MU Tracker] Found status: $status");
                break;
            }
        }

        // Additional Dragon MU status detection - look for status in character info section
        if ($status === 'Offline') {
            $statusNodes = $xpath->query("//*[contains(text(), 'Character Information')]/following-sibling::*//*[contains(text(), 'Online') or contains(text(), 'Offline')]");
            foreach ($statusNodes as $node) {
                $text = trim($node->textContent);
                if (preg_match('/(Online|Offline)/', $text, $matches)) {
                    $status = $matches[1];
                    error_log("[MU Tracker] Found status in character info: $status");
                    break;
                }
            }
        }

        // get last URL path segment (character slug) to locate the right block
        $path = parse_url($url, PHP_URL_PATH) ?: '';
        $charSlug = trim(basename($path));
        $charSlugDecoded = urldecode($charSlug);
        $charSlugLower = strtolower($charSlugDecoded);

        // --- find a context node near the character ---
        $contextNode = null;

        // safe XPath helper to do case-insensitive contains for the slug (escape single quotes)
        $escapeForXpath = function (string $s) {
            if (strpos($s, "'") === false) {
                return "'" . $s . "'";
            }
            // build concat('a', "'", 'b') style
            $parts = explode("'", $s);
            $concatParts = array_map(function ($p) {
                return "'" . $p . "'";
            }, $parts);
            $expr = "concat(" . implode(", \"'\", ", $concatParts) . ")";
            return $expr;
        };

        if ($charSlugLower !== '') {
            // Try to find nodes whose normalized text contains the slug (case-insensitive)
            $slugExpr = "contains(translate(normalize-space(.), 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), " . $escapeForXpath($charSlugLower) . ")";
            $candidates = $xpath->query("//*[$slugExpr]");
            if ($candidates->length) {
                // pick the candidate that looks like a character block by climbing parents
                for ($i = 0; $i < $candidates->length; $i++) {
                    $first = $candidates->item($i);
                    $parent = $first;
                    for ($depth = 0; $depth < 6 && $parent->parentNode; $depth++) {
                        $parent = $parent->parentNode;
                        if ($parent->nodeType === XML_ELEMENT_NODE && $parent instanceof DOMElement) {
                            $classAttr = strtolower($parent->getAttribute('class') ?? '');
                            $idAttr = strtolower($parent->getAttribute('id') ?? '');
                            $nodeName = strtolower($parent->nodeName);
                            if (
                                str_contains($classAttr, 'character') || str_contains($classAttr, 'profile')
                                || str_contains($classAttr, 'char-info') || str_contains($idAttr, 'character')
                                || $nodeName === 'section' || $nodeName === 'article' || $nodeName === 'div'
                            ) {
                                $contextNode = $parent;
                                break 2; // stop both loops
                            }
                        }
                    }
                }
                if (!$contextNode) {
                    // last-resort: immediate parent of the first candidate
                    $contextNode = $candidates->item(0)->parentNode;
                }
            }
        }

        // fallback: common selectors
        if (!$contextNode) {
            $common = $xpath->query("//div[contains(@class,'character') or contains(@class,'profile') or contains(@class,'char-info') or contains(@id,'character') or contains(@class,'player')]");
            if ($common->length) {
                $contextNode = $common->item(0);
            }
        }

        // Utility: check a DOMElement for online/offline signals in a robust way
        $checkElemForStatus = function (DOMElement $el) {
            $text = trim(preg_replace('/\s+/', ' ', $el->textContent ?? ''));
            $lower = strtolower($text);

            // direct textual matches
            if (preg_match('/(^|\W)online(\W|$)/i', $text)) return 'Online';
            if (preg_match('/(^|\W)offline(\W|$)/i', $text)) return 'Offline';

            // attributes
            $attrs = [];
            foreach ($el->attributes ?? [] as $a) {
                $attrs[] = strtolower($a->nodeName . ':' . $a->nodeValue);
            }
            $attrsStr = implode(' ', $attrs);
            if (strpos($attrsStr, 'data-status:online') !== false) return 'Online';
            if (strpos($attrsStr, 'data-status:offline') !== false) return 'Offline';
            if (strpos($attrsStr, 'class:online') !== false) return 'Online';
            if (strpos($attrsStr, 'class:offline') !== false) return 'Offline';
            if (strpos($attrsStr, 'aria-label:online') !== false) return 'Online';
            if (strpos($attrsStr, 'aria-label:offline') !== false) return 'Offline';
            if (strpos($attrsStr, 'title:online') !== false) return 'Online';
            if (strpos($attrsStr, 'title:offline') !== false) return 'Offline';
            if (strpos($attrsStr, 'alt:online') !== false) return 'Online';
            if (strpos($attrsStr, 'alt:offline') !== false) return 'Offline';

            return null;
        };

        // 1) First priority: explicit "Status" label followed by value within the context node (if available)
        $foundStatus = null;
        $statusNodes = [];
        if ($contextNode) {
            // search only within contextNode for "Status" label forms
            $labelNodes = $xpath->query(".//label[translate(normalize-space(.), 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz') = 'status'] | .//*[translate(normalize-space(.), 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz') = 'status']", $contextNode);
            foreach ($labelNodes as $ln) {
                // try sibling or following element
                $sibling = null;
                if ($ln->nextSibling && $ln->nextSibling->nodeType === XML_ELEMENT_NODE) {
                    $sibling = $ln->nextSibling;
                } else {
                    // try following sibling element
                    $siblings = $xpath->query("following-sibling::*[1]", $ln);
                    if ($siblings->length) $sibling = $siblings->item(0);
                }
                if ($sibling) {
                    $res = $checkElemForStatus($sibling);
                    if ($res !== null) {
                        $foundStatus = $res;
                        break;
                    }
                    $statusNodes[] = $sibling;
                }
            }
        }

        // 2) Second priority: direct elements in context with class/id/attrs containing 'status', 'online', 'offline'
        if (!$foundStatus && $contextNode) {
            $candidates = $xpath->query(".//*[contains(translate(@class,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz'),'status') or contains(translate(@class,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz'),'online') or contains(translate(@class,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz'),'offline') or @data-status or @aria-label or @title]", $contextNode);
            foreach ($candidates as $cand) {
                $res = $checkElemForStatus($cand);
                if ($res !== null) {
                    $foundStatus = $res;
                    break;
                }
                $statusNodes[] = $cand;
            }
        }

        // 3) Third priority: images/icons near context that may have alt/title
        if (!$foundStatus && $contextNode) {
            $imgs = $xpath->query(".//img | .//svg | .//i", $contextNode);
            foreach ($imgs as $img) {
                $res = $checkElemForStatus($img);
                if ($res !== null) {
                    $foundStatus = $res;
                    break;
                }
            }
        }

        // 4) Fourth: short text nodes near the character name. Avoid large containers to reduce false positives.
        if (!$foundStatus && $contextNode) {
            $textNodes = $xpath->query(".//text()[string-length(normalize-space(.)) > 0 and string-length(normalize-space(.)) < 120]", $contextNode);
            foreach ($textNodes as $t) {
                $parent = $t->parentNode;
                if (!$parent instanceof DOMElement) continue;
                $txt = trim($t->textContent);
                if (preg_match('/(^|\W)online(\W|$)/i', $txt)) {
                    $foundStatus = 'Online';
                    break;
                }
                if (preg_match('/(^|\W)offline(\W|$)/i', $txt)) {
                    $foundStatus = 'Offline';
                    break;
                }
            }
        }

        // 5) Last resort inside the whole page but *very conservative*: only if there's a *single* occurrence of 'online' and no 'offline',
        // and the page does not contain obvious global counters like "players online" near that match. We avoid this unless nothing else found.
        if (!$foundStatus) {
            // search whole page but prefer to ensure that match is short and not in a global header/footer
            $anyOnline = $xpath->query("//*[contains(translate(normalize-space(.), 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'online')]");
            $anyOffline = $xpath->query("//*[contains(translate(normalize-space(.), 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'offline')]");
            if ($anyOnline->length && $anyOffline->length === 0) {
                // pick the shortest node containing 'online' (likely to be status label, not long paragraph)
                $shortest = null;
                $shortLen = PHP_INT_MAX;
                foreach ($anyOnline as $n) {
                    $len = mb_strlen(trim($n->textContent));
                    if ($len > 0 && $len < $shortLen) {
                        $shortLen = $len;
                        $shortest = $n;
                    }
                }
                if ($shortest && $shortLen < 120) {
                    $foundStatus = 'Online';
                }
            }
        }

        if ($foundStatus !== null) {
            $status = $foundStatus;
        } else {
            // try explicit labeled field outside the context node as a fallback
            $statusText = $getText('Status');
            if ($statusText !== 'Unknown') {
                if (stripos($statusText, 'online') !== false) $status = 'Online';
                if (stripos($statusText, 'offline') !== false) $status = 'Offline';
            }
        }

        // Helpful debug logging (toggle or remove in production)
        // Log when ambiguous: if context node exists but we had to fallback
        if (isset($contextNode) && $contextNode) {
            // Note: logging whole node would be heavy; log summary
            error_log("[MU Tracker] scrapeCharacterData: url={$url} slug={$charSlugLower} detected_status={$status}");
        }

        return [
            'name'         => $name,
            'status'       => $status,
            'level'        => $level,
            'resets'       => $resets,
            'grand_resets' => $grandResets,
            'class'        => $class,
            'gens'         => $gens,
            'guild'        => $guild,
            'location'     => $location,
        ];
    } catch (\Exception $e) {
        error_log('[MU Tracker] Scrape error: ' . $e->getMessage());
        return [
            'name'         => 'Unknown',
            'status'       => 'Error',
            'level'        => 0,
            'resets'       => 0,
            'grand_resets' => 0,
            'class'        => 'Unknown',
            'gens'         => 'Unknown',
            'guild'        => 'Unknown',
            'location'     => 'Unknown',
        ];
    }
}


/**
 * Add a new character to track
 */
function addCharacter($url)
{
    global $auth;

    $pdo = getDatabase();
    if (!$pdo) {
        return ['success' => false, 'message' => 'Database connection failed'];
    }

    $user_id = $auth->getCurrentUserId();
    if (!$user_id) {
        return ['success' => false, 'message' => 'User not authenticated'];
    }

    // Check character limit for non-VIP/admin users
    if (!$auth->isVipOrAdmin()) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM characters WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch();

        if ($result && $result['count'] >= 10) {
            // Log failed character addition due to limit
            $currentUser = $auth->getCurrentUser();
            logActivity($currentUser['username'], 'Character addition failed', "Failed to add character - character limit reached (10/10)", $_SERVER['REMOTE_ADDR'] ?? 'unknown');
            return ['success' => false, 'message' => 'Character limit reached. You can track up to 10 characters. Upgrade to VIP for unlimited characters.'];
        }
    }

    try {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            // Log failed character addition due to invalid URL
            $currentUser = $auth->getCurrentUser();
            logActivity($currentUser['username'], 'Character addition failed', "Failed to add character - invalid URL format: $url", $_SERVER['REMOTE_ADDR'] ?? 'unknown');
            return ['success' => false, 'message' => 'Invalid URL format'];
        }

        $stmt = $pdo->prepare("SELECT id FROM characters WHERE character_url = ? AND user_id = ?");
        $stmt->execute([$url, $user_id]);
        if ($stmt->fetch()) {
            // Log failed character addition due to duplicate
            $currentUser = $auth->getCurrentUser();
            logActivity($currentUser['username'], 'Character addition failed', "Failed to add character - URL already exists: $url", $_SERVER['REMOTE_ADDR'] ?? 'unknown');
            return ['success' => false, 'message' => 'Character URL already exists for this user'];
        }

        // Scrape character data to get the name
        $characterData = scrapeCharacterData($url);
        error_log("[MU Tracker] Scraped data for URL $url: " . json_encode($characterData));

        if (!$characterData || $characterData['name'] === 'Unknown') {
            // Log failed character addition due to scraping failure
            $currentUser = $auth->getCurrentUser();
            logActivity($currentUser['username'], 'Character addition failed', "Failed to add character - could not scrape data from URL: $url", $_SERVER['REMOTE_ADDR'] ?? 'unknown');
            return ['success' => false, 'message' => 'Could not fetch character data from the provided URL. Please check the URL and try again.'];
        }

        $name = $characterData['name'];

        // Insert character with all scraped data
        $stmt = $pdo->prepare("INSERT INTO characters (name, character_url, user_id, status, level, resets, grand_resets, class, guild, gens, location) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $name,
            $url,
            $user_id,
            $characterData['status'] ?? 'Unknown',
            $characterData['level'] ?? 0,
            $characterData['resets'] ?? 0,
            $characterData['grand_resets'] ?? 0,
            $characterData['class'] ?? 'Unknown',
            $characterData['guild'] ?? 'Unknown',
            $characterData['gens'] ?? 'Unknown',
            $characterData['location'] ?? 'Unknown'
        ]);

        $characterId = $pdo->lastInsertId();

        // Log character addition with detailed info
        $currentUser = $auth->getCurrentUser();
        $character_info = "Added character: $name (Level: {$characterData['level']}, Resets: {$characterData['resets']}, Status: {$characterData['status']}, Class: {$characterData['class']})";
        logActivity($currentUser['username'], 'Character added', $character_info, $_SERVER['REMOTE_ADDR'] ?? 'unknown');

        // Update analytics with the scraped data
        updateCharacterDataWithAnalytics($characterId, $url);

        // Return the complete character data for immediate display
        return [
            'success' => true,
            'message' => 'Character added successfully',
            'character' => [
                'id' => $characterId,
                'name' => $name,
                'character_url' => $url,
                'status' => $characterData['status'] ?? 'Unknown',
                'level' => $characterData['level'] ?? 0,
                'resets' => $characterData['resets'] ?? 0,
                'grand_resets' => $characterData['grand_resets'] ?? 0,
                'class' => $characterData['class'] ?? 'Unknown',
                'guild' => $characterData['guild'] ?? 'Unknown',
                'gens' => $characterData['gens'] ?? 'Unknown',
                'location' => $characterData['location'] ?? 'Unknown',
                'last_updated' => date('Y-m-d H:i:s')
            ]
        ];
    } catch (PDOException $e) {
        logError("Error adding character: " . $e->getMessage());
        return ['success' => false, 'message' => 'Database error occurred'];
    }
}

/**
 * Remove a character from tracking
 */
function removeCharacter($id)
{
    global $auth;

    $pdo = getDatabase();
    if (!$pdo) {
        return ['success' => false, 'message' => 'Database connection failed'];
    }

    $user_id = $auth->getCurrentUserId();
    if (!$user_id) {
        return ['success' => false, 'message' => 'User not authenticated'];
    }

    try {
        // First get character info before deletion for logging
        $stmt = $pdo->prepare("SELECT name, level, resets, status FROM characters WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $user_id]);
        $character = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$character) {
            // Log failed character removal
            $currentUser = $auth->getCurrentUser();
            logActivity($currentUser['username'], 'Character removal failed', "Failed to remove character ID: $id - character not found or access denied", $_SERVER['REMOTE_ADDR'] ?? 'unknown');
            return ['success' => false, 'message' => 'Character not found or access denied'];
        }

        $stmt = $pdo->prepare("DELETE FROM characters WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $user_id]);

        if ($stmt->rowCount() > 0) {
            // Log character removal with detailed info
            $currentUser = $auth->getCurrentUser();
            $character_info = "Removed character: {$character['name']} (Level: {$character['level']}, Resets: {$character['resets']}, Status: {$character['status']})";
            logActivity($currentUser['username'], 'Character removed', $character_info, $_SERVER['REMOTE_ADDR'] ?? 'unknown');

            return ['success' => true, 'message' => 'Character removed successfully'];
        } else {
            // Log failed character removal
            $currentUser = $auth->getCurrentUser();
            logActivity($currentUser['username'], 'Character removal failed', "Failed to remove character ID: $id - no rows affected", $_SERVER['REMOTE_ADDR'] ?? 'unknown');
            return ['success' => false, 'message' => 'Character not found or access denied'];
        }
    } catch (PDOException $e) {
        logError("Error removing character: " . $e->getMessage());
        // Log failed character removal due to database error
        $currentUser = $auth->getCurrentUser();
        logActivity($currentUser['username'], 'Character removal failed', "Database error removing character ID: $id - " . $e->getMessage(), $_SERVER['REMOTE_ADDR'] ?? 'unknown');
        return ['success' => false, 'message' => 'Database error occurred'];
    }
}

/**
 * Get all tracked characters
 */
function getAllCharacters()
{
    global $auth;

    $pdo = getDatabase();
    if (!$pdo) {
        return [];
    }

    $user_id = $auth->getCurrentUserId();
    if (!$user_id) {
        return [];
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM characters WHERE user_id = ? ORDER BY name ASC");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logError("Error getting characters: " . $e->getMessage());
        return [];
    }
}

/**
 * Refresh all character data with analytics
 */
function refreshAllCharacters()
{
    $characters = getAllCharacters();
    $updated    = 0;
    $errors     = 0;

    foreach ($characters as $character) {
        if (updateCharacterDataWithAnalytics($character['id'], $character['character_url'])) {
            $updated++;
        } else {
            $errors++;
        }
        usleep(500000); // 0.5 second delay
    }

    // Log refresh activity with detailed statistics
    global $auth;
    $currentUser = $auth->getCurrentUser();
    $total_characters = count($characters);
    $success_rate = $total_characters > 0 ? round(($updated / $total_characters) * 100, 1) : 0;
    $refresh_details = "Refreshed $updated/$total_characters characters (Success rate: {$success_rate}%)" . ($errors > 0 ? " with $errors errors" : "");
    logActivity($currentUser['username'], 'Characters refreshed', $refresh_details, $_SERVER['REMOTE_ADDR'] ?? 'unknown');

    return [
        'success' => true,
        'message' => "Updated {$updated} characters" . ($errors > 0 ? " ({$errors} errors)" : ""),
        'updated' => $updated,
        'errors'  => $errors
    ];
}

/**
 * Refresh individual character
 */
function refreshCharacter($characterId)
{
    global $auth;

    $pdo = getDatabase();
    if (!$pdo) {
        return ['success' => false, 'message' => 'Database connection failed'];
    }

    $user_id = $auth->getCurrentUserId();
    if (!$user_id) {
        return ['success' => false, 'message' => 'User not authenticated'];
    }

    try {
        // Get character info first
        $stmt = $pdo->prepare("SELECT name, character_url FROM characters WHERE id = ? AND user_id = ?");
        $stmt->execute([$characterId, $user_id]);
        $character = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$character) {
            // Log failed character refresh
            $currentUser = $auth->getCurrentUser();
            logActivity($currentUser['username'], 'Character refresh failed', "Failed to refresh character ID: $characterId - character not found or access denied", $_SERVER['REMOTE_ADDR'] ?? 'unknown');
            return ['success' => false, 'message' => 'Character not found or access denied'];
        }

        // Update character data
        $success = updateCharacterDataWithAnalytics($characterId, $character['character_url']);

        if ($success) {
            // Log successful character refresh
            $currentUser = $auth->getCurrentUser();
            logActivity($currentUser['username'], 'Character refreshed', "Refreshed character: {$character['name']}", $_SERVER['REMOTE_ADDR'] ?? 'unknown');
            return ['success' => true, 'message' => 'Character refreshed successfully'];
        } else {
            // Log failed character refresh
            $currentUser = $auth->getCurrentUser();
            logActivity($currentUser['username'], 'Character refresh failed', "Failed to refresh character: {$character['name']} - scraping error", $_SERVER['REMOTE_ADDR'] ?? 'unknown');
            return ['success' => false, 'message' => 'Failed to refresh character data'];
        }
    } catch (PDOException $e) {
        logError("Error refreshing character: " . $e->getMessage());
        // Log failed character refresh due to database error
        $currentUser = $auth->getCurrentUser();
        logActivity($currentUser['username'], 'Character refresh failed', "Database error refreshing character ID: $characterId - " . $e->getMessage(), $_SERVER['REMOTE_ADDR'] ?? 'unknown');
        return ['success' => false, 'message' => 'Database error occurred'];
    }
}

/**
 * Update character data (legacy)
 */
function updateCharacterData($characterId, $url)
{
    return updateCharacterDataWithAnalytics($characterId, $url);
}

/**
 * Get character by ID
 */
function getCharacterById($id)
{
    $pdo = getDatabase();
    if (!$pdo) {
        return false;
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM characters WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logError("Error getting character by ID: " . $e->getMessage());
        return false;
    }
}

/**
 * Update character status manually
 */
function updateCharacterStatus($id, $status)
{
    $pdo = getDatabase();
    if (!$pdo) {
        return false;
    }

    try {
        $stmt = $pdo->prepare(
            "UPDATE characters SET status = ?, last_updated = CURRENT_TIMESTAMP WHERE id = ?"
        );
        $stmt->execute([$status, $id]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        logError("Error updating character status: " . $e->getMessage());
        return false;
    }
}

/**
 * Get detailed character dashboard data
 */
function getCharacterDashboard($characterId, $days = 30)
{
    $pdo = getDatabase();
    if (!$pdo) return [];

    try {
        // 1. Character basic info
        $stmt = $pdo->prepare("SELECT * FROM characters WHERE id = ?");
        $stmt->execute([$characterId]);
        $character = $stmt->fetch(PDO::FETCH_ASSOC);

        // 2. Daily progress
        $stmt = $pdo->prepare("
            SELECT date, starting_level, ending_level, levels_gained,
                   starting_resets, ending_resets, resets_gained,
                   starting_grand_resets, ending_grand_resets, grand_resets_gained
            FROM daily_progress
            WHERE character_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
            ORDER BY date ASC
        ");
        $stmt->execute([$characterId, $days]);
        $dailyProgress = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 3. Hourly analytics (last 24 hours)
        $stmt = $pdo->prepare("
            SELECT date, hour, level_start, level_end, levels_gained,
                   resets_start, resets_end, resets_gained
            FROM hourly_analytics
            WHERE character_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL 1 DAY)
            ORDER BY date ASC, hour ASC
        ");
        $stmt->execute([$characterId]);
        $hourlyAnalytics = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 4. Recent milestones
        $stmt = $pdo->prepare("
            SELECT milestone_type, old_value, new_value, achieved_at
            FROM level_milestones
            WHERE character_id = ?
            ORDER BY achieved_at DESC
            LIMIT 10
        ");
        $stmt->execute([$characterId]);
        $milestones = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 5. Period analytics (weekly & monthly)
        $stmt = $pdo->prepare("
            SELECT period_type, year, period_number,
                   levels_gained, resets_gained, grand_resets_gained, active_days
            FROM period_analytics
            WHERE character_id = ?
            ORDER BY year DESC, period_number DESC
        ");
        $stmt->execute([$characterId]);
        $periodAnalytics = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'character' => $character,
            'daily_progress' => $dailyProgress,
            'hourly_analytics' => $hourlyAnalytics,
            'milestones' => $milestones,
            'period_analytics' => $periodAnalytics
        ];
    } catch (Exception $e) {
        logError("Error fetching character dashboard: " . $e->getMessage());
        return [];
    }
}

/**
 * Get leaderboard for a given period
 */
function getLeaderboard($period = 'week', $limit = 10)
{
    $pdo = getDatabase();
    if (!$pdo) return [];

    $interval = match ($period) {
        'day' => '1 DAY',
        'week' => '7 DAY',
        'month' => '30 DAY',
        default => '7 DAY'
    };

    $limit = (int)$limit; // ensure it's an integer to prevent SQL injection

    $sql = "
        SELECT c.id, c.name, c.level AS current_level,
               SUM(dp.levels_gained) AS levels_gained,
               SUM(dp.resets_gained) AS resets_gained,
               AVG(dp.levels_gained) AS avg_levels_per_day
        FROM characters c
        LEFT JOIN daily_progress dp
            ON dp.character_id = c.id
            AND dp.date >= DATE_SUB(CURDATE(), INTERVAL $interval)
        GROUP BY c.id
        ORDER BY levels_gained DESC, resets_gained DESC
        LIMIT $limit
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get character progression chart data (for chart.js or frontend)
 */
function getProgressionChartData($characterId, $days = 30)
{
    $pdo = getDatabase();
    if (!$pdo) return [];

    $stmt = $pdo->prepare("
        SELECT date, ending_level, ending_resets, ending_grand_resets
        FROM daily_progress
        WHERE character_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
        ORDER BY date ASC
    ");
    $stmt->execute([$characterId, $days]);
    $progress = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $labels = [];
    $levels = [];
    $resets = [];
    $grandResets = [];

    foreach ($progress as $row) {
        $labels[] = $row['date'];
        $levels[] = (int)$row['ending_level'];
        $resets[] = (int)$row['ending_resets'];
        $grandResets[] = (int)$row['ending_grand_resets'];
    }

    return [
        'labels' => $labels,
        'levels' => $levels,
        'resets' => $resets,
        'grand_resets' => $grandResets
    ];
}

/**
 * Fallback function to fetch content using cURL with cross-browser compatibility
 */
function fetchWithCurl($url)
{
    // Try multiple user agents with cURL as well
    $userAgents = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:109.0) Gecko/20100101 Firefox/121.0',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.1 Safari/605.1.15',
        'Mozilla/5.0 (Linux; Android 10; SM-G973F) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36'
    ];

    foreach ($userAgents as $userAgent) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_ENCODING, 'gzip,deflate,br'); // Support compression
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.9,es;q=0.8,pt;q=0.7',
            'Accept-Encoding: gzip, deflate, br',
            'Connection: keep-alive',
            'Upgrade-Insecure-Requests: 1',
            'Cache-Control: max-age=0',
            'DNT: 1'
        ]);
        curl_setopt($ch, CURLOPT_COOKIEJAR, '/tmp/cookies.txt'); // Enable cookies
        curl_setopt($ch, CURLOPT_COOKIEFILE, '/tmp/cookies.txt');

        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        // Check if we got valid HTML content
        if ($html !== false && $httpCode === 200 && strlen($html) > 100 && (strpos($html, '<html') !== false || strpos($html, '<HTML') !== false || strpos($html, '<!DOCTYPE') !== false)) {
            error_log("[MU Tracker] cURL success with User-Agent: " . substr($userAgent, 0, 50) . "... (Length: " . strlen($html) . ")");
            return $html;
        } else {
            error_log('[MU Tracker] cURL failed with ' . substr($userAgent, 0, 50) . '...: ' . $error . ' (HTTP: ' . $httpCode . ', Length: ' . strlen($html) . ')');
        }
    }

    error_log('[MU Tracker] All cURL User-Agent attempts failed');
    return false;
}

// ============================================================================
// ADMIN PANEL FUNCTIONS
// ============================================================================

/**
 * Get admin dashboard statistics
 */
function getAdminStats()
{
    $pdo = getDatabase();
    if (!$pdo) {
        return [
            'total_users' => 0,
            'total_characters' => 0,
            'active_licenses' => 0,
            'vip_users' => 0
        ];
    }

    try {
        // Total users
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
        $total_users = $stmt->fetch()['count'];

        // Total characters
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM characters");
        $total_characters = $stmt->fetch()['count'];

        // Active licenses
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM license_keys WHERE is_used = 0");
        $active_licenses = $stmt->fetch()['count'];

        // VIP users
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE user_role IN ('vip', 'admin')");
        $vip_users = $stmt->fetch()['count'];

        return [
            'total_users' => $total_users,
            'total_characters' => $total_characters,
            'active_licenses' => $active_licenses,
            'vip_users' => $vip_users
        ];
    } catch (PDOException $e) {
        logError("Error getting admin stats: " . $e->getMessage());
        return [
            'total_users' => 0,
            'total_characters' => 0,
            'active_licenses' => 0,
            'vip_users' => 0
        ];
    }
}

/**
 * Get all license keys
 */
function getAllLicenses()
{
    $pdo = getDatabase();
    if (!$pdo) {
        return ['success' => false, 'message' => 'Database connection failed'];
    }

    try {
        $stmt = $pdo->query("SELECT * FROM license_keys ORDER BY created_at DESC");
        return ['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
    } catch (PDOException $e) {
        logError("Error getting licenses: " . $e->getMessage());
        return ['success' => false, 'message' => 'Database error occurred'];
    }
}

/**
 * Create a new license key
 */
function createLicense($license_key, $max_uses = 1, $expires_at = null, $license_type = 'regular')
{
    $pdo = getDatabase();
    if (!$pdo) {
        return ['success' => false, 'message' => 'Database connection failed'];
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO license_keys (license_key, license_type, max_uses, expires_at) VALUES (?, ?, ?, ?)");
        $stmt->execute([$license_key, $license_type, $max_uses, $expires_at]);

        logActivity('admin', 'Created license key', "License: $license_key, Type: $license_type, Max uses: $max_uses");
        return ['success' => true, 'message' => 'License key created successfully'];
    } catch (PDOException $e) {
        logError("Error creating license: " . $e->getMessage());
        return ['success' => false, 'message' => 'Database error occurred'];
    }
}

/**
 * Update a license key
 */
function updateLicense($id, $license_key, $max_uses, $expires_at, $license_type = 'regular')
{
    $pdo = getDatabase();
    if (!$pdo) {
        return ['success' => false, 'message' => 'Database connection failed'];
    }

    try {
        $stmt = $pdo->prepare("UPDATE license_keys SET license_key = ?, license_type = ?, max_uses = ?, expires_at = ? WHERE id = ?");
        $stmt->execute([$license_key, $license_type, $max_uses, $expires_at, $id]);

        logActivity('admin', 'Updated license key', "ID: $id, License: $license_key, Type: $license_type");
        return ['success' => true, 'message' => 'License key updated successfully'];
    } catch (PDOException $e) {
        logError("Error updating license: " . $e->getMessage());
        return ['success' => false, 'message' => 'Database error occurred'];
    }
}

/**
 * Delete a license key
 */
function deleteLicense($id)
{
    $pdo = getDatabase();
    if (!$pdo) {
        return ['success' => false, 'message' => 'Database connection failed'];
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM license_keys WHERE id = ?");
        $stmt->execute([$id]);

        logActivity('admin', 'Deleted license key', "ID: $id");
        return ['success' => true, 'message' => 'License key deleted successfully'];
    } catch (PDOException $e) {
        logError("Error deleting license: " . $e->getMessage());
        return ['success' => false, 'message' => 'Database error occurred'];
    }
}

/**
 * Get all users with character counts
 */
function getAllUsers()
{
    $pdo = getDatabase();
    if (!$pdo) {
        return ['success' => false, 'message' => 'Database connection failed'];
    }

    try {
        $stmt = $pdo->query("
            SELECT u.*, 
                   COUNT(c.id) as character_count
            FROM users u 
            LEFT JOIN characters c ON u.id = c.user_id 
            GROUP BY u.id 
            ORDER BY u.created_at DESC
        ");
        return ['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
    } catch (PDOException $e) {
        logError("Error getting users: " . $e->getMessage());
        return ['success' => false, 'message' => 'Database error occurred'];
    }
}

/**
 * Update user role
 */
function updateUserRole($user_id, $role)
{
    $pdo = getDatabase();
    if (!$pdo) {
        return ['success' => false, 'message' => 'Database connection failed'];
    }

    try {
        $stmt = $pdo->prepare("UPDATE users SET user_role = ? WHERE id = ?");
        $stmt->execute([$role, $user_id]);

        logActivity('admin', 'Updated user role', "User ID: $user_id, Role: $role");
        return ['success' => true, 'message' => 'User role updated successfully'];
    } catch (PDOException $e) {
        logError("Error updating user role: " . $e->getMessage());
        return ['success' => false, 'message' => 'Database error occurred'];
    }
}

/**
 * Toggle user status
 */
function toggleUserStatus($user_id)
{
    $pdo = getDatabase();
    if (!$pdo) {
        return ['success' => false, 'message' => 'Database connection failed'];
    }

    try {
        // Get current status
        $stmt = $pdo->prepare("SELECT is_active FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        if (!$user) {
            return ['success' => false, 'message' => 'User not found'];
        }

        $new_status = $user['is_active'] ? 0 : 1;
        $stmt = $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ?");
        $stmt->execute([$new_status, $user_id]);

        $action = $new_status ? 'activated' : 'deactivated';
        logActivity('admin', "User $action", "User ID: $user_id");
        return ['success' => true, 'message' => "User $action successfully"];
    } catch (PDOException $e) {
        logError("Error toggling user status: " . $e->getMessage());
        return ['success' => false, 'message' => 'Database error occurred'];
    }
}

/**
 * Get all characters for admin view
 */
function getAllCharactersAdmin()
{
    $pdo = getDatabase();
    if (!$pdo) {
        return ['success' => false, 'message' => 'Database connection failed'];
    }

    try {
        $stmt = $pdo->query("
            SELECT c.*, u.username 
            FROM characters c 
            LEFT JOIN users u ON c.user_id = u.id 
            ORDER BY c.created_at DESC
        ");
        return ['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
    } catch (PDOException $e) {
        logError("Error getting all characters: " . $e->getMessage());
        return ['success' => false, 'message' => 'Database error occurred'];
    }
}

/**
 * Delete character (admin function)
 */
function deleteCharacterAdmin($character_id)
{
    $pdo = getDatabase();
    if (!$pdo) {
        return ['success' => false, 'message' => 'Database connection failed'];
    }

    try {
        // Get character info for logging
        $stmt = $pdo->prepare("SELECT name, user_id FROM characters WHERE id = ?");
        $stmt->execute([$character_id]);
        $character = $stmt->fetch();

        if (!$character) {
            return ['success' => false, 'message' => 'Character not found'];
        }

        // Delete character
        $stmt = $pdo->prepare("DELETE FROM characters WHERE id = ?");
        $stmt->execute([$character_id]);

        logActivity('admin', 'Deleted character', "Character: {$character['name']}, User ID: {$character['user_id']}");
        return ['success' => true, 'message' => 'Character deleted successfully'];
    } catch (PDOException $e) {
        logError("Error deleting character: " . $e->getMessage());
        return ['success' => false, 'message' => 'Database error occurred'];
    }
}

/**
 * Get user characters
 */
function getUserCharacters($user_id)
{
    $pdo = getDatabase();
    if (!$pdo) {
        return ['success' => false, 'message' => 'Database connection failed'];
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM characters WHERE user_id = ? ORDER BY name ASC");
        $stmt->execute([$user_id]);
        return ['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
    } catch (PDOException $e) {
        logError("Error getting user characters: " . $e->getMessage());
        return ['success' => false, 'message' => 'Database error occurred'];
    }
}

/**
 * Get activity logs
 */
function getActivityLogs($limit = 50)
{
    $pdo = getDatabase();
    if (!$pdo) {
        return ['success' => false, 'message' => 'Database connection failed'];
    }

    try {
        // Create activity_logs table if it doesn't exist
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS activity_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user VARCHAR(100) NOT NULL,
                action VARCHAR(255) NOT NULL,
                details TEXT,
                ip_address VARCHAR(45),
                timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user (user),
                INDEX idx_timestamp (timestamp)
            )
        ");

        $stmt = $pdo->prepare("SELECT * FROM activity_logs ORDER BY timestamp DESC LIMIT ?");
        $stmt->execute([$limit]);
        return ['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
    } catch (PDOException $e) {
        logError("Error getting activity logs: " . $e->getMessage());
        return ['success' => false, 'message' => 'Database error occurred'];
    }
}

/**
 * Get character rankings for dashboard
 */
function getCharacterRankings($period = 'day', $metric = 'resets_gained', $isVip = false)
{
    $pdo = getDatabase();
    if (!$pdo) {
        return ['success' => false, 'message' => 'Database connection failed'];
    }

    try {
        // Determine the date range based on period and VIP status
        $dateCondition = '';

        switch ($period) {
            case 'day':
                $dateCondition = "dp.date = CURDATE()";
                break;
            case 'week':
                $dateCondition = "dp.date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
                break;
            case 'month':
                // VIP users get 6 months, regular users get 30 days
                $days = $isVip ? 180 : 30;
                $dateCondition = "dp.date >= DATE_SUB(CURDATE(), INTERVAL {$days} DAY)";
                break;
            case 'year':
                // VIP-only feature: yearly analytics
                if ($isVip) {
                    $dateCondition = "dp.date >= DATE_SUB(CURDATE(), INTERVAL 365 DAY)";
                } else {
                    $dateCondition = "dp.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
                }
                break;
        }

        // Get all characters with their current data (for rankings, we need all characters)
        $pdo = getDatabase();
        $stmt = $pdo->query("SELECT id, name, level, resets, grand_resets FROM characters ORDER BY name ASC");
        $characters = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($characters)) {
            return [
                'success' => true,
                'data' => [
                    'top_performers' => [],
                    'best_day_records' => [],
                    'most_consistent' => [],
                    'most_improved' => [],
                    'most_efficient' => []
                ]
            ];
        }

        $characterIds = array_column($characters, 'id');
        $placeholders = str_repeat('?,', count($characterIds) - 1) . '?';

        // Get comprehensive analytics data for all characters in one optimized query
        $stmt = $pdo->prepare("
            SELECT 
                dp.character_id,
                c.name as character_name,
                SUM(dp.resets_gained) as total_resets_gained,
                SUM(dp.grand_resets_gained) as total_grand_resets_gained,
                MAX(dp.ending_level) as max_level,
                MAX(dp.ending_resets) as max_resets,
                MAX(dp.ending_grand_resets) as max_grand_resets,
                COUNT(DISTINCT dp.date) as active_days,
                AVG(dp.resets_gained) as avg_resets_gained,
                STDDEV(dp.resets_gained) as stddev_resets_gained,
                MIN(dp.date) as first_activity,
                MAX(dp.date) as last_activity,
                COUNT(dp.id) as total_records
            FROM daily_progress dp
            JOIN characters c ON dp.character_id = c.id
            WHERE dp.character_id IN ($placeholders) AND $dateCondition
            GROUP BY dp.character_id, c.name
            ORDER BY total_resets_gained DESC
        ");
        $stmt->execute($characterIds);
        $analyticsData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // If no analytics data, create rankings based on current character data
        if (empty($analyticsData)) {
            $rankings = [];
            foreach ($characters as $character) {
                $value = 0;
                switch ($metric) {
                    case 'resets_gained':
                        $value = 0; // No historical data
                        break;
                    case 'grand_resets_gained':
                        $value = 0; // No historical data
                        break;
                    case 'level':
                        $value = (int)($character['level'] ?? 0);
                        break;
                    case 'resets':
                        $value = (int)($character['resets'] ?? 0);
                        break;
                    case 'grand_resets':
                        $value = (int)($character['grand_resets'] ?? 0);
                        break;
                }

                $rankings[] = [
                    'character_id' => $character['id'],
                    'character_name' => $character['name'],
                    'value' => $value,
                    'active_days' => 1,
                    'avg_value' => $value,
                    'consistency_score' => 100, // Perfect consistency for current data
                    'improvement_rate' => $value,
                    'efficiency_score' => $value,
                    'total_records' => 1,
                    'activity_span' => 1,
                    'first_activity' => date('Y-m-d'),
                    'last_activity' => date('Y-m-d')
                ];
            }
        } else {
            // Process analytics data and calculate comprehensive rankings
            $rankings = [];

            foreach ($analyticsData as $data) {
                $characterId = $data['character_id'];
                $characterName = $data['character_name'];

                // Calculate value based on selected metric with proper type casting
                $value = 0;
                switch ($metric) {
                    case 'resets_gained':
                        $value = (float)($data['total_resets_gained'] ?? 0);
                        break;
                    case 'grand_resets_gained':
                        $value = (float)($data['total_grand_resets_gained'] ?? 0);
                        break;
                    case 'level':
                        $value = (int)($data['max_level'] ?? 0);
                        break;
                    case 'resets':
                        $value = (int)($data['max_resets'] ?? 0);
                        break;
                    case 'grand_resets':
                        $value = (int)($data['max_grand_resets'] ?? 0);
                        break;
                }

                // Calculate advanced consistency score using coefficient of variation
                $avgValue = (float)($data['avg_resets_gained'] ?? 0);
                $stdDev = (float)($data['stddev_resets_gained'] ?? 0);
                $consistencyScore = 0;

                if ($avgValue > 0 && $stdDev > 0) {
                    $coefficientOfVariation = $stdDev / $avgValue;
                    $consistencyScore = max(0, min(100, 100 - ($coefficientOfVariation * 100)));
                } elseif ($avgValue > 0 && $stdDev == 0) {
                    $consistencyScore = 100; // Perfect consistency
                }

                // Calculate improvement rate (value per active day)
                $activeDays = (int)($data['active_days'] ?? 0);
                $improvementRate = $activeDays > 0 ? $value / $activeDays : 0;

                // Calculate efficiency score (value per record)
                $totalRecords = (int)($data['total_records'] ?? 0);
                $efficiencyScore = $totalRecords > 0 ? $value / $totalRecords : 0;

                // Calculate activity span (days between first and last activity)
                $firstActivity = $data['first_activity'] ? new DateTime($data['first_activity']) : null;
                $lastActivity = $data['last_activity'] ? new DateTime($data['last_activity']) : null;
                $activitySpan = 0;
                if ($firstActivity && $lastActivity) {
                    $activitySpan = $firstActivity->diff($lastActivity)->days + 1;
                }

                $rankings[] = [
                    'character_id' => $characterId,
                    'character_name' => $characterName,
                    'value' => $value,
                    'active_days' => $activeDays,
                    'avg_value' => round($avgValue, 2),
                    'consistency_score' => round($consistencyScore, 2),
                    'improvement_rate' => round($improvementRate, 2),
                    'efficiency_score' => round($efficiencyScore, 2),
                    'total_records' => $totalRecords,
                    'activity_span' => $activitySpan,
                    'first_activity' => $data['first_activity'],
                    'last_activity' => $data['last_activity']
                ];
            }
        }

        // Get best day records for each character with optimized query
        $bestDayRecords = [];
        if (!empty($analyticsData)) {
            foreach ($characterIds as $characterId) {
                $stmt = $pdo->prepare("
                    SELECT 
                        dp.date,
                        SUM(dp.resets_gained) as daily_resets_gained,
                        SUM(dp.grand_resets_gained) as daily_grand_resets_gained,
                        MAX(dp.ending_level) as daily_max_level,
                        MAX(dp.ending_resets) as daily_max_resets,
                        MAX(dp.ending_grand_resets) as daily_max_grand_resets
                    FROM daily_progress dp
                    WHERE dp.character_id = ? AND $dateCondition
                    GROUP BY dp.date
                    ORDER BY daily_resets_gained DESC
                    LIMIT 1
                ");
                $stmt->execute([$characterId]);
                $bestDay = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($bestDay) {
                    $value = 0;
                    switch ($metric) {
                        case 'resets_gained':
                            $value = (float)($bestDay['daily_resets_gained'] ?? 0);
                            break;
                        case 'grand_resets_gained':
                            $value = (float)($bestDay['daily_grand_resets_gained'] ?? 0);
                            break;
                        case 'level':
                            $value = (int)($bestDay['daily_max_level'] ?? 0);
                            break;
                        case 'resets':
                            $value = (int)($bestDay['daily_max_resets'] ?? 0);
                            break;
                        case 'grand_resets':
                            $value = (int)($bestDay['daily_max_grand_resets'] ?? 0);
                            break;
                    }

                    // Find character name efficiently
                    $characterName = '';
                    foreach ($characters as $char) {
                        if ($char['id'] == $characterId) {
                            $characterName = $char['name'];
                            break;
                        }
                    }

                    $bestDayRecords[] = [
                        'character_id' => $characterId,
                        'character_name' => $characterName,
                        'date' => $bestDay['date'],
                        'value' => $value
                    ];
                }
            }
        } else {
            // If no analytics data, still try to get best day records from daily_progress
            foreach ($characterIds as $characterId) {
                $stmt = $pdo->prepare("
                    SELECT 
                        dp.date,
                        SUM(dp.resets_gained) as daily_resets_gained,
                        SUM(dp.grand_resets_gained) as daily_grand_resets_gained,
                        MAX(dp.ending_level) as daily_max_level,
                        MAX(dp.ending_resets) as daily_max_resets,
                        MAX(dp.ending_grand_resets) as daily_max_grand_resets
                    FROM daily_progress dp
                    WHERE dp.character_id = ? AND $dateCondition
                    GROUP BY dp.date
                    ORDER BY daily_resets_gained DESC
                    LIMIT 1
                ");
                $stmt->execute([$characterId]);
                $bestDay = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($bestDay) {
                    $value = 0;
                    switch ($metric) {
                        case 'resets_gained':
                            $value = (float)($bestDay['daily_resets_gained'] ?? 0);
                            break;
                        case 'grand_resets_gained':
                            $value = (float)($bestDay['daily_grand_resets_gained'] ?? 0);
                            break;
                        case 'level':
                            $value = (int)($bestDay['daily_max_level'] ?? 0);
                            break;
                        case 'resets':
                            $value = (int)($bestDay['daily_max_resets'] ?? 0);
                            break;
                        case 'grand_resets':
                            $value = (int)($bestDay['daily_max_grand_resets'] ?? 0);
                            break;
                    }

                    // Find character name efficiently
                    $characterName = '';
                    foreach ($characters as $char) {
                        if ($char['id'] == $characterId) {
                            $characterName = $char['name'];
                            break;
                        }
                    }

                    $bestDayRecords[] = [
                        'character_id' => $characterId,
                        'character_name' => $characterName,
                        'date' => $bestDay['date'],
                        'value' => $value
                    ];
                }
            }
        }

        // Sort and get top performers
        usort($rankings, function ($a, $b) {
            return $b['value'] - $a['value'];
        });
        $topPerformers = array_slice($rankings, 0, 5);

        // Sort and get best day records
        usort($bestDayRecords, function ($a, $b) {
            return $b['value'] - $a['value'];
        });
        $bestDayRecords = array_slice($bestDayRecords, 0, 5);

        // Get most consistent (by consistency score)
        $mostConsistent = $rankings;
        usort($mostConsistent, function ($a, $b) {
            return $b['consistency_score'] - $a['consistency_score'];
        });
        $mostConsistent = array_slice($mostConsistent, 0, 5);

        // Get most improved (by improvement rate)
        $mostImproved = $rankings;
        usort($mostImproved, function ($a, $b) {
            return $b['improvement_rate'] - $a['improvement_rate'];
        });
        $mostImproved = array_slice($mostImproved, 0, 5);

        // Get most efficient (by efficiency score)
        $mostEfficient = $rankings;
        usort($mostEfficient, function ($a, $b) {
            return $b['efficiency_score'] - $a['efficiency_score'];
        });
        $mostEfficient = array_slice($mostEfficient, 0, 5);

        return [
            'success' => true,
            'data' => [
                'top_performers' => $topPerformers,
                'best_day_records' => $bestDayRecords,
                'most_consistent' => $mostConsistent,
                'most_improved' => $mostImproved,
                'most_efficient' => $mostEfficient,
                'period' => $period,
                'metric' => $metric,
                'total_characters' => count($rankings),
                'generated_at' => date('Y-m-d H:i:s')
            ]
        ];
    } catch (PDOException $e) {
        logError("Error getting character rankings: " . $e->getMessage());
        return ['success' => false, 'message' => 'Database error occurred'];
    }
}

/**
 * Get VIP-extended character analytics with 6+ months of data
 */
function getVipCharacterAnalytics($characterId, $period = 'month')
{
    global $auth;

    $pdo = getDatabase();
    if (!$pdo) {
        return ['success' => false, 'message' => 'Database connection failed'];
    }

    // Check if user is VIP
    if (!$auth->isVipOrAdmin()) {
        return ['success' => false, 'message' => 'VIP access required for extended analytics'];
    }

    try {
        // Determine date range based on period
        $days = match ($period) {
            'week' => 7,
            'month' => 30,
            'quarter' => 90,
            'half_year' => 180,
            'year' => 365,
            default => 30
        };

        // Get character info
        $stmt = $pdo->prepare("SELECT * FROM characters WHERE id = ?");
        $stmt->execute([$characterId]);
        $character = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$character) {
            return ['success' => false, 'message' => 'Character not found'];
        }

        // Get extended daily progress data
        $stmt = $pdo->prepare("
            SELECT 
                date,
                starting_level, ending_level, levels_gained,
                starting_resets, ending_resets, resets_gained,
                starting_grand_resets, ending_grand_resets, grand_resets_gained
            FROM daily_progress 
            WHERE character_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
            ORDER BY date ASC
        ");
        $stmt->execute([$characterId, $days]);
        $dailyProgress = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate extended analytics
        $totalResets = array_sum(array_column($dailyProgress, 'resets_gained'));
        $totalGrandResets = array_sum(array_column($dailyProgress, 'grand_resets_gained'));
        $activeDays = count(array_filter($dailyProgress, fn($day) => $day['resets_gained'] > 0 || $day['grand_resets_gained'] > 0));
        $avgResetsPerDay = $activeDays > 0 ? round($totalResets / $activeDays, 2) : 0;

        // Find best and worst days
        $bestDay = null;
        $worstDay = null;
        foreach ($dailyProgress as $day) {
            if ($day['resets_gained'] > 0) {
                if (!$bestDay || $day['resets_gained'] > $bestDay['resets_gained']) {
                    $bestDay = $day;
                }
                if (!$worstDay || $day['resets_gained'] < $worstDay['resets_gained']) {
                    $worstDay = $day;
                }
            }
        }

        // Calculate trends (VIP-only feature)
        $trends = calculateTrends($dailyProgress);

        return [
            'success' => true,
            'data' => [
                'character' => $character,
                'period' => $period,
                'days_analyzed' => $days,
                'total_resets_gained' => $totalResets,
                'total_grand_resets_gained' => $totalGrandResets,
                'active_days' => $activeDays,
                'avg_resets_per_day' => $avgResetsPerDay,
                'best_day' => $bestDay,
                'worst_day' => $worstDay,
                'daily_progress' => $dailyProgress,
                'trends' => $trends,
                'is_vip' => true
            ]
        ];
    } catch (Exception $e) {
        logError("Error getting VIP character analytics: " . $e->getMessage());
        return ['success' => false, 'message' => 'Database error occurred'];
    }
}

/**
 * Calculate trends for VIP analytics
 */
function calculateTrends($dailyProgress)
{
    if (count($dailyProgress) < 7) {
        return ['trend' => 'insufficient_data', 'direction' => 'stable'];
    }

    $recentDays = array_slice($dailyProgress, -7);
    $olderDays = array_slice($dailyProgress, 0, -7);

    $recentAvg = array_sum(array_column($recentDays, 'resets_gained')) / count($recentDays);
    $olderAvg = count($olderDays) > 0 ? array_sum(array_column($olderDays, 'resets_gained')) / count($olderDays) : $recentAvg;

    $trend = 'stable';
    $direction = 'stable';

    if ($recentAvg > $olderAvg * 1.1) {
        $trend = 'improving';
        $direction = 'up';
    } elseif ($recentAvg < $olderAvg * 0.9) {
        $trend = 'declining';
        $direction = 'down';
    }

    return [
        'trend' => $trend,
        'direction' => $direction,
        'recent_avg' => round($recentAvg, 2),
        'older_avg' => round($olderAvg, 2),
        'improvement_rate' => round((($recentAvg - $olderAvg) / max($olderAvg, 1)) * 100, 2)
    ];
}

/**
 * Export character data to CSV (VIP feature)
 */
function exportCharacterData($characterId, $format = 'csv')
{
    global $auth;

    if (!$auth->isVipOrAdmin()) {
        return ['success' => false, 'message' => 'VIP access required for data export'];
    }

    $pdo = getDatabase();
    if (!$pdo) {
        return ['success' => false, 'message' => 'Database connection failed'];
    }

    try {
        // Get character info
        $stmt = $pdo->prepare("SELECT * FROM characters WHERE id = ?");
        $stmt->execute([$characterId]);
        $character = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$character) {
            return ['success' => false, 'message' => 'Character not found'];
        }

        // Get extended data (6 months for VIP)
        $stmt = $pdo->prepare("
            SELECT 
                date,
                starting_level, ending_level, levels_gained,
                starting_resets, ending_resets, resets_gained,
                starting_grand_resets, ending_grand_resets, grand_resets_gained
            FROM daily_progress 
            WHERE character_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL 180 DAY)
            ORDER BY date ASC
        ");
        $stmt->execute([$characterId]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($format === 'csv') {
            return exportToCSV($character, $data);
        }

        return ['success' => false, 'message' => 'Unsupported export format'];
    } catch (Exception $e) {
        logError("Error exporting character data: " . $e->getMessage());
        return ['success' => false, 'message' => 'Export failed'];
    }
}

/**
 * Export data to CSV format
 */
function exportToCSV($character, $data)
{
    $filename = "character_{$character['name']}_export_" . date('Y-m-d') . ".csv";

    // Set headers for file download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

    // Create output stream
    $output = fopen('php://output', 'w');

    // Add BOM for UTF-8 Excel compatibility
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    // Write character info header
    fputcsv($output, ['Character Export Report']);
    fputcsv($output, ['Character Name', $character['name']]);
    fputcsv($output, ['Current Level', $character['level']]);
    fputcsv($output, ['Current Resets', $character['resets']]);
    fputcsv($output, ['Current Grand Resets', $character['grand_resets']]);
    fputcsv($output, ['Class', $character['class']]);
    fputcsv($output, ['Guild', $character['guild']]);
    fputcsv($output, ['Status', $character['status']]);
    fputcsv($output, ['Last Updated', $character['last_updated']]);
    fputcsv($output, ['Export Date', date('Y-m-d H:i:s')]);
    fputcsv($output, []); // Empty line

    // Write data headers
    fputcsv($output, [
        'Date',
        'Starting Level',
        'Ending Level',
        'Levels Gained',
        'Starting Resets',
        'Ending Resets',
        'Resets Gained',
        'Starting Grand Resets',
        'Ending Grand Resets',
        'Grand Resets Gained'
    ]);

    // Write data rows
    foreach ($data as $row) {
        fputcsv($output, [
            $row['date'],
            $row['starting_level'],
            $row['ending_level'],
            $row['levels_gained'],
            $row['starting_resets'],
            $row['ending_resets'],
            $row['resets_gained'],
            $row['starting_grand_resets'],
            $row['ending_grand_resets'],
            $row['grand_resets_gained']
        ]);
    }

    fclose($output);
    exit;
}

/**
 * Log activity
 */
function logActivity($user, $action, $details = '', $ip_address = null)
{
    $pdo = getDatabase();
    if (!$pdo) return false;

    try {
        // Create activity_logs table if it doesn't exist
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS activity_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user VARCHAR(100) NOT NULL,
                action VARCHAR(255) NOT NULL,
                details TEXT,
                ip_address VARCHAR(45),
                timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user (user),
                INDEX idx_timestamp (timestamp)
            )
        ");

        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (user, action, details, ip_address) 
            VALUES (?, ?, ?, ?)
        ");

        $ip = $ip_address ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

        return $stmt->execute([$user, $action, $details, $ip]);
    } catch (PDOException $e) {
        logError("Error logging activity: " . $e->getMessage());
        return false;
    }
}
