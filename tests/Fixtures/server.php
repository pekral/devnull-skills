<?php

declare(strict_types=1);

/**
 * Router for `php -S`: a stand-in for dev-null.sh. The first path segment names the scenario, so one server serves
 * every test: http://127.0.0.1:<port>/<scenario>/log/latest.
 *
 * Scenarios: ok, noisy (the noise patterns of a real run), changed, noversion, flaky (429 once), redeploy (version
 * changes mid-run), badreplies (replies 500).
 */

$path = (string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
[$scenario, $route] = array_pad(explode('/', ltrim($path, '/'), 2), 2, '');
$base = "http://{$_SERVER['HTTP_HOST']}/{$scenario}";
$version = $_SERVER['HTTP_X_INERTIA_VERSION'] ?? null;

$json = static function (mixed $data, int $status = 200, array $headers = []): bool {
    http_response_code($status);
    header('Content-Type: application/json');
    foreach ($headers as $name => $value) {
        header("{$name}: {$value}");
    }
    echo json_encode($data);

    return true;
};

// Counts requests per scenario across `php -S` requests, which share no memory.
$hit = static function (string $key): int {
    $file = sys_get_temp_dir().'/devnull-fixture-'.$_SERVER['SERVER_PORT'].'-'.$key;
    $count = (int) @file_get_contents($file) + 1;
    file_put_contents($file, (string) $count);

    return $count;
};

$post = static fn (string $id, string $handle, int $ageMinutes, string $body, int $stars = 0, int $replies = 0, array $extra = []): array => array_replace([
    'id' => $id,
    'body' => $body,
    'created_at' => gmdate('Y-m-d\TH:i:s.000000\Z', time() - $ageMinutes * 60),
    'stars_count' => $stars,
    'replies_count' => $replies,
    'author' => ['handle' => $handle, 'bio' => "bio of {$handle}"],
    'link_preview' => null,
    'quoted_commit' => null,
], $extra);

$pages = [
    1 => [
        $post('p1', 'alice', 10, 'Shipping a new Laravel package for queues', 5, 2),
        $post('p2', 'bob', 20, 'Weekend hiking photos', 9),
    ],
    2 => [
        $post('p3', 'carol', 30, 'Which PHP coding standard do you use?', 1, 0, ['link_preview' => ['url' => 'https://example.com', 'title' => 'Pint docs', 'description' => 'Laravel Pint']]),
        $post('p4', 'dave', 60 * 48, 'Old Laravel post outside the window'),
    ],
];

$noisy = [
    1 => [
        ...array_map(static fn (int $n): array => $post("r{$n}", 'reelbot', 5 + $n, "Merged in reelhouse: feat: change {$n}, pull request #{$n}", extra: ['link_preview' => ['url' => "https://github.com/x/reelhouse/pull/{$n}", 'title' => "feat: change {$n} · Pull Request #{$n}"]]), range(1, 5)),
        $post('n1', 'puzzler', 20, 'Cryptogram 345: I cracked the code with my skills'),
        $post('n2', 'robots', 25, 'My robots.txt analyzer scores the crawl rules of any site'),
        $post('s1', 'pestcore', 30, 'Someone made a fake copy of a Claude skill with a backdoor. Review skills like Composer dependencies.', 4, 2),
        $post('s2', 'maintainer', 35, 'Agentic PR floods hit open source maintainers hard', 0, 4),
    ],
];

if ($scenario === '__hits') {
    // Reads a request counter without counting: /__hits/<scenario>
    return $json(['count' => (int) @file_get_contents(sys_get_temp_dir().'/devnull-fixture-'.$_SERVER['SERVER_PORT'].'-log-'.$route)]);
}

if ($route === 'log/latest') {
    $hit("log-{$scenario}");
    if ($scenario === 'noversion') {
        return $json(['message' => 'Not Found'], 404);
    }

    $current = $scenario === 'redeploy' && $hit('redeploy-pages') > 1 ? 'v2' : 'v1';
    if ($version !== $current) {
        return $json(null, 409, ['X-Inertia-Version' => $current]);
    }

    if ($scenario === 'flaky' && $hit('flaky') === 1) {
        return $json(['message' => 'Too Many Attempts.'], 429);
    }

    if ($scenario === 'changed') {
        return $json(['component' => 'log/show', 'props' => ['posts' => []], 'version' => $current]);
    }

    $page = (int) ($_GET['cursor'] ?? 1);
    $pages = $scenario === 'noisy' ? $noisy : $pages;

    return $json([
        'component' => 'log/show',
        'version' => $current,
        'props' => ['commits' => [
            'data' => $pages[$page] ?? [],
            'next_page_url' => isset($pages[$page + 1]) ? "{$base}/log/latest?cursor=".($page + 1) : null,
        ]],
    ]);
}

if (preg_match('#^commits/([^/]+)/replies$#', $route, $match) === 1) {
    if ($scenario === 'badreplies') {
        return $json(['message' => 'Server Error'], 500);
    }

    return $json(['data' => [
        ['body' => 'Looks great, does it support Horizon?', 'stars_count' => 1, 'author' => ['handle' => 'erin']],
        ['body' => 'Using it already', 'stars_count' => 0, 'author' => ['handle' => 'frank']],
    ], 'next_cursor' => null]);
}

return $json(['message' => 'Not Found'], 404);
