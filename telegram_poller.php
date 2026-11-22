<?php
/**
 * Telegram Bot Poller for GitHub Actions
 * Runs every 5 min via scheduled workflow, polls getUpdates, processes commands.
 *
 * Required secrets/env vars:
 * - TELEGRAM_BOT_TOKEN
 * - TELEGRAM_ALLOWED_USERS (comma-separated user IDs)
 * - GITHUB_TOKEN (provided by Actions automatically)
 * - GITHUB_REPOSITORY (provided by Actions automatically)
 */

require_once __DIR__ . "/env_loader.php";
loadEnv(__DIR__ . "/.env");

$botToken = getenv("TELEGRAM_BOT_TOKEN") ?: $_ENV["TELEGRAM_BOT_TOKEN"] ?? "";
$allowedUsers = array_map('trim', explode(",", getenv("TELEGRAM_ALLOWED_USERS") ?: $_ENV["TELEGRAM_ALLOWED_USERS"] ?? ""));
$githubToken = getenv("GITHUB_TOKEN") ?: $_ENV["GITHUB_TOKEN"] ?? "";
$githubRepo = getenv("GITHUB_REPOSITORY") ?: $_ENV["GITHUB_REPOSITORY"] ?? "";
$offsetFile = __DIR__ . "/.telegram_offset";

if (!$botToken || !$githubToken || !$githubRepo) {
    die("Missing required env vars\n");
}

// Read last offset
$offset = 0;
if (file_exists($offsetFile)) {
    $offset = (int) file_get_contents($offsetFile);
}

function tgRequest(string $method, array $params = [], string $token): ?array {
    $ch = curl_init("https://api.telegram.org/bot{$token}/{$method}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code !== 200 || !$resp) return null;
    return json_decode($resp, true);
}

function ghRequest(string $method, string $endpoint, array $body = [], string $token, string $repo): ?array {
    $url = "https://api.github.com/repos/{$repo}/{$endpoint}";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer {$token}",
        "Accept: application/vnd.github+json",
        "X-GitHub-Api-Version: 2022-11-28",
        "Content-Type: application/json",
        "User-Agent: MangaReader-TelegramPoller"
    ]);
    if ($body) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code < 200 || $code >= 300) {
        error_log("GitHub API {$method} {$endpoint} failed: {$code} - {$resp}");
        return null;
    }
    return $resp ? json_decode($resp, true) : [];
}

function sendMessage(string $chatId, string $text, string $token): void {
    tgRequest("sendMessage", [
        "chat_id" => $chatId,
        "text" => $text,
        "parse_mode" => "HTML",
        "disable_web_page_preview" => true
    ], $token);
}

function triggerWorkflowDispatch(string $token, string $repo, string $workflowFile = "manga_cron.yml"): bool {
    $result = ghRequest("POST", "actions/workflows/{$workflowFile}/dispatches", [
        "ref" => "master"
    ], $token, $repo);
    return $result !== null;
}

function triggerTrendingWorkflowDispatch(string $token, string $repo): bool {
    return triggerWorkflowDispatch($token, $repo, "manga_trending_cron.yml");
}

function getWorkflowStatus(string $token, string $repo, string $workflowFile = "manga_cron.yml"): ?array {
    return ghRequest("GET", "actions/workflows/{$workflowFile}", [], $token, $repo);
}

function disableWorkflow(string $token, string $repo, string $workflowFile = "manga_cron.yml"): bool {
    $result = ghRequest("PUT", "actions/workflows/{$workflowFile}/disable", [], $token, $repo);
    return $result !== null;
}

function enableWorkflow(string $token, string $repo, string $workflowFile = "manga_cron.yml"): bool {
    $result = ghRequest("PUT", "actions/workflows/{$workflowFile}/enable", [], $token, $repo);
    return $result !== null;
}

// Poll updates
$updates = tgRequest("getUpdates", [
    "offset" => $offset,
    "timeout" => 10,
    "allowed_updates" => ["message"]
], $botToken);

if (!$updates || !isset($updates["ok"]) || !$updates["ok"]) {
    die("Failed to get updates\n");
}

$newOffset = $offset;
foreach ($updates["result"] as $update) {
    $newOffset = max($newOffset, $update["update_id"] + 1);

    if (!isset($update["message"])) continue;
    $msg = $update["message"];
    $chatId = $msg["chat"]["id"] ?? null;
    $userId = $msg["from"]["id"] ?? null;
    $text = $msg["text"] ?? "";

    if (!$chatId || !$userId) continue;

    // Auth check
    if (!in_array((string)$userId, $allowedUsers)) {
        sendMessage($chatId, "❌ Unauthorized.", $botToken);
        continue;
    }

    $cmd = trim($text);
    $response = "";

    if ($cmd === "/sync_now" || $cmd === "/sync") {
        $response = "🔄 Triggering manual sync (manga + trending) via workflow_dispatch...";
        sendMessage($chatId, $response, $botToken);
        $mangaOk = triggerWorkflowDispatch($githubToken, $githubRepo, "manga_cron.yml");
        $trendingOk = triggerWorkflowDispatch($githubToken, $githubRepo, "manga_trending_cron.yml");
        if ($mangaOk && $trendingOk) {
            sendMessage($chatId, "✅ Both sync workflows triggered!", $botToken);
        } elseif ($mangaOk) {
            sendMessage($chatId, "⚠️ Manga sync triggered, but trending sync failed.", $botToken);
        } elseif ($trendingOk) {
            sendMessage($chatId, "⚠️ Trending sync triggered, but manga sync failed.", $botToken);
        } else {
            sendMessage($chatId, "❌ Failed to trigger both workflows.", $botToken);
        }
        continue;
    }

    if ($cmd === "/sync_manga") {
        $response = "🔄 Triggering manga sync only...";
        sendMessage($chatId, $response, $botToken);
        if (triggerWorkflowDispatch($githubToken, $githubRepo, "manga_cron.yml")) {
            sendMessage($chatId, "✅ Manga sync workflow triggered!", $botToken);
        } else {
            sendMessage($chatId, "❌ Failed to trigger manga sync workflow.", $botToken);
        }
        continue;
    }

    if ($cmd === "/sync_trending") {
        $response = "🔄 Triggering trending sync only...";
        sendMessage($chatId, $response, $botToken);
        if (triggerWorkflowDispatch($githubToken, $githubRepo, "manga_trending_cron.yml")) {
            sendMessage($chatId, "✅ Trending sync workflow triggered!", $botToken);
        } else {
            sendMessage($chatId, "❌ Failed to trigger trending sync workflow.", $botToken);
        }
        continue;
    }

    if ($cmd === "/enable_cron") {
        $response = "🔄 Enabling scheduled workflow...";
        sendMessage($chatId, $response, $botToken);
        if (enableWorkflow($githubToken, $githubRepo)) {
            sendMessage($chatId, "✅ Scheduled workflow enabled!", $botToken);
        } else {
            sendMessage($chatId, "❌ Failed to enable workflow.", $botToken);
        }
        continue;
    }

    if ($cmd === "/disable_cron") {
        $response = "🔄 Disabling scheduled workflow...";
        sendMessage($chatId, $response, $botToken);
        if (disableWorkflow($githubToken, $githubRepo)) {
            sendMessage($chatId, "✅ Scheduled workflow disabled!", $botToken);
        } else {
            sendMessage($chatId, "❌ Failed to disable workflow.", $botToken);
        }
        continue;
    }

    if ($cmd === "/status") {
        $wf = getWorkflowStatus($githubToken, $githubRepo);
        $state = $wf ? ($wf["state"] ?? "unknown") : "unknown";
        $response = "📊 <b>Manga Reader Status</b>\n\n";
        $response .= "Workflow: <b>{$state}</b>\n";
        $response .= "Repo: <code>{$githubRepo}</code>";
        sendMessage($chatId, $response, $botToken);
        continue;
    }

    if (str_starts_with($cmd, "/help")) {
        $response = "🤖 <b>Manga Reader Bot Commands</b>\n\n";
        $response .= "<b>Sync Commands:</b>\n";
        $response .= "/sync_now /sync - Trigger both manga & trending sync\n";
        $response .= "/sync_manga - Trigger manga sync only\n";
        $response .= "/sync_trending - Trigger trending sync only\n\n";
        $response .= "<b>Cron Control:</b>\n";
        $response .= "/enable_cron - Enable scheduled sync\n";
        $response .= "/disable_cron - Disable scheduled sync\n\n";
        $response .= "<b>Info:</b>\n";
        $response .= "/status - Check workflow status\n";
        sendMessage($chatId, $response, $botToken);
        continue;
    }

    // Unknown command
    sendMessage($chatId, "❓ Unknown command. Use /help", $botToken);
}

// Save new offset
file_put_contents($offsetFile, (string)$newOffset);
echo "Processed " . count($updates["result"]) . " updates. Next offset: {$newOffset}\n";