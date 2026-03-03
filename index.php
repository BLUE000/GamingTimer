<?php
$csv_path = __DIR__ . '/Config/games.csv';
$game_templates = [];

if (file_exists($csv_path)) {
    $csv_content = file_get_contents($csv_path);
    if ($csv_content !== false) {
        // Remove UTF-8 BOM if present
        $bom = pack('H*','EFBBBF');
        $csv_content = preg_replace("/^$bom/", '', $csv_content);
        
        // Normalize line endings to \n
        $csv_content = str_replace(array("\r\n", "\r"), "\n", $csv_content);
        
        $lines = explode("\n", $csv_content);
        
        $index = 0;
        // Skip header line (index 0)
        for ($i = 1; $i < count($lines); $i++) {
            $line = trim($lines[$i]);
            if (empty($line)) continue;
            
            $data = str_getcsv($line); // parses a single line safely
            if (count($data) >= 3) {
                // Keep the raw string, do not preg_replace Japanese characters
                $title = trim($data[0]);
                $setting = trim($data[1]);
                $real_seconds_per_game_minute = (float)trim($data[2]);
                
                // 表示用名前
                $display_name = $title . ($setting !== '-' && $setting !== '' ? " ({$setting})" : '');
                
                // リアルタイム同期かどうかを簡易判定（秒数が60なら現実と同じ1:1進行）
                $mode = ($real_seconds_per_game_minute == 60) ? 'realtime' : 'session';
                
                // ROLC Special Case: 120 real minutes = 24 game hours
                if (strpos(strtoupper($title), 'ROLC') !== false || strpos($title, '120分周期') !== false) {
                    $mode = 'rolc';
                    $phases = [
                        [ 'start' => 0, 'name' => 'NIGHT', 'icon' => '🌙', 'color' => '#3b82f6' ],
                        [ 'start' => 4, 'name' => 'MORNING', 'icon' => '🌅', 'color' => '#fba744' ],
                        [ 'start' => 12, 'name' => 'EVENING', 'icon' => '🌇', 'color' => '#f97316' ],
                        [ 'start' => 16, 'name' => 'NIGHT', 'icon' => '🌙', 'color' => '#3b82f6' ]
                    ];
                } else {
                    $phases = [
                        [ 'start' => 5, 'name' => 'MORNING', 'icon' => '🌅', 'color' => '#fba744' ],
                        [ 'start' => 10, 'name' => 'DAY', 'icon' => '☀️', 'color' => '#f59e0b' ],
                        [ 'start' => 17, 'name' => 'EVENING', 'icon' => '🌇', 'color' => '#f97316' ],
                        [ 'start' => 20, 'name' => 'NIGHT', 'icon' => '🌙', 'color' => '#3b82f6' ]
                    ];
                }

                $game_templates['game_' . $index] = [
                    'name' => $display_name,
                    'mode' => $mode,
                    'desc' => "1ゲーム分 = 現実の {$real_seconds_per_game_minute} 秒",
                    'realSecondsPerGameMinute' => $real_seconds_per_game_minute,
                    'phases' => $phases
                ];
                $index++;
            }
        }
    }
} 

// CSV読み込みに失敗、または1件もデータが無い場合のフォールバック
if (empty($game_templates)) {
    // CSVが無い場合のフォールバックデータ
    $game_templates['hunter'] = [
        'name' => 'theHunter (標準)',
        'mode' => 'session',
        'desc' => '1ゲーム分 = 現実の 15 秒',
        'realSecondsPerGameMinute' => 15,
        'phases' => [
            [ 'start' => 5, 'name' => 'MORNING', 'icon' => '🌅', 'color' => '#fba744' ],
            [ 'start' => 9, 'name' => 'DAY', 'icon' => '☀️', 'color' => '#f59e0b' ],
            [ 'start' => 17, 'name' => 'EVENING', 'icon' => '🌇', 'color' => '#f97316' ],
            [ 'start' => 20, 'name' => 'NIGHT', 'icon' => '🌙', 'color' => '#3b82f6' ]
        ]
    ];
}

// サーバー内のフォントファイルを取得
$fonts_dir = __DIR__ . '/Fonts';
$server_fonts = [];
if (is_dir($fonts_dir)) {
    $files = scandir($fonts_dir);
    foreach ($files as $file) {
        if (preg_match('/\.(ttf|otf|woff|woff2)$/i', $file)) {
            $server_fonts[] = $file;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gaming Timer</title>
    <!-- Google Fonts including modern sans-serif and a digital 7-segment style font -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;500;700&family=Oswald:wght@400;700&family=Share+Tech+Mono&display=swap" rel="stylesheet">
    
    <style>
        /* Inject Server Fonts dynamically */
        <?php foreach ($server_fonts as $font_file): 
            $font_name = pathinfo($font_file, PATHINFO_FILENAME);
        ?>
        @font-face {
            font-family: '<?php echo $font_name; ?>';
            src: url('Fonts/<?php echo $font_file; ?>');
        }
        <?php endforeach; ?>

        :root {
            --bg-color: #0f172a;
            --panel-bg: rgba(30, 41, 59, 0.7);
            --primary-color: #f59e0b;
            --accent-color: #ef4444;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --glow: 0 0 20px rgba(245, 158, 11, 0.5);
            
            /* User Customizable Global Variables */
            --clock-font: 'Share Tech Mono', monospace;
            --clock-size: 4rem;
            --clock-color: #f8fafc;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background-color: var(--bg-color);
            background-image: 
                radial-gradient(circle at 15% 50%, rgba(245, 158, 11, 0.05), transparent 25%),
                radial-gradient(circle at 85% 30%, rgba(239, 68, 68, 0.05), transparent 25%);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            overflow-x: hidden;
            transition: background-color 0.5s ease, background-image 1s ease;
        }

        .container {
            background: var(--panel-bg);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px;
            padding: 40px;
            width: 100%;
            max-width: 600px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            text-align: center;
            position: relative;
            z-index: 10;
        }

        h1 {
            font-family: 'Oswald', sans-serif;
            font-size: 2rem;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 30px;
            background: linear-gradient(90deg, var(--primary-color), #fcd34d);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 0 2px 10px rgba(0,0,0, 0.2);
            transition: all 0.5s ease;
        }

        /* Form Elements */
        .time-form {
            display: flex;
            flex-direction: column;
            gap: 20px;
            margin-bottom: 20px;
        }

        .input-group {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 8px;
            text-align: left;
        }

        label {
            font-size: 0.9rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 500;
        }

        select, input[type="time"] {
            width: 100%;
            padding: 16px;
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            color: var(--text-main);
            font-family: 'Outfit', sans-serif;
            font-size: 1.2rem;
            outline: none;
            transition: all 0.3s ease;
            appearance: none;
        }

        select {
            cursor: pointer;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='white' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 1em;
        }
        
        select option {
            background-color: var(--bg-color);
            color: var(--text-main);
        }

        select:focus, input[type="time"]:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 15px rgba(245, 158, 11, 0.3);
        }

        button {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            color: white;
            border: none;
            padding: 16px 32px;
            font-size: 1.1rem;
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
            border-radius: 12px;
            cursor: pointer;
            text-transform: uppercase;
            letter-spacing: 1px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            transition: all 0.3s ease;
            margin-top: 20px;
            width: 100%;
        }

        button:hover {
            transform: translateY(-2px);
            filter: brightness(1.1);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: none;
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .help-text {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-top: 15px;
            line-height: 1.5;
        }

        /* Clock Display */
        .clock-container {
            display: none;
            flex-direction: column;
            align-items: center;
            animation: pop-in 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
        }

        @keyframes pop-in {
            0% { transform: scale(0.9); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }

        .digital-clock {
            font-family: var(--clock-font);
            font-size: var(--clock-size);
            color: var(--clock-color);
            font-weight: 700;
            line-height: 1;
            letter-spacing: 2px;
            text-shadow: 0 0 10px rgba(0,0,0,0.5);
            margin-bottom: 20px;
            position: relative;
            transition: color 0.3s ease, font-size 0.3s ease;
            font-variant-numeric: tabular-nums; /* Monospace numbers */
        }
        
        /* 7-Segment Toggle Hack using CSS Variable class fallback if requested */
        .digital-clock.force-segment {
             font-family: 'Share Tech Mono', monospace !important;
        }

        .digital-clock span.colon {
            animation: pulse-colon 1s infinite alternate;
            position: relative;
            top: -2px;
        }

        @keyframes pulse-colon {
            0% { opacity: 1; }
            100% { opacity: 0.2; }
        }

        .time-period {
            font-size: 1.5rem;
            color: var(--primary-color);
            text-transform: uppercase;
            letter-spacing: 3px;
            margin-top: 5px;
            font-weight: 700;
            transition: color 0.5s ease;
        }

        .clock-ring {
            position: relative;
            width: 200px;
            height: 200px;
            margin: 0 auto 30px;
        }

        .clock-ring svg {
            transform: rotate(-90deg);
        }

        .ring-bg {
            fill: none;
            stroke: rgba(255, 255, 255, 0.05);
            stroke-width: 8;
        }

        .ring-progress {
            fill: none;
            stroke: var(--primary-color);
            stroke-width: 8;
            stroke-linecap: round;
            stroke-dasharray: 565.48;
            stroke-dashoffset: 0;
            filter: drop-shadow(0 0 8px var(--primary-color));
            transition: stroke-dashoffset 0.1s linear, stroke 0.5s ease;
        }

        .ring-inner {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .ring-inner .icon {
            font-size: 3rem;
            margin-bottom: 5px;
            filter: drop-shadow(0 2px 10px rgba(0,0,0,0.5));
        }

        .btn-reset {
            background: rgba(255,255,255,0.05);
            color: var(--text-muted);
            box-shadow: none;
            padding: 10px 20px;
            font-size: 0.9rem;
            border: 1px solid rgba(255,255,255,0.1);
        }
        /* UI Settings Panel overlay */
        .settings-panel {
            display: none;
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: var(--panel-bg);
            border-radius: 24px;
            padding: 30px;
            text-align: left;
            z-index: 20;
            flex-direction: column;
            gap: 15px;
            overflow-y: auto;
        }
        
        .settings-panel.active {
            display: flex;
            animation: fade-in 0.3s ease forwards;
        }

        .setting-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }

        .setting-item label {
            font-size: 0.95rem;
            color: var(--text-main);
        }

        .color-picker {
            -webkit-appearance: none;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            padding: 0;
            overflow: hidden;
            background: none;
        }
        .color-picker::-webkit-color-swatch-wrapper {
            padding: 0;
        }
        .color-picker::-webkit-color-swatch {
            border: 2px solid rgba(255,255,255,0.2);
            border-radius: 50%;
        }

        .range-slider {
            width: 150px;
            accent-color: var(--primary-color);
        }

    </style>
</head>
<body>

    <div class="container" id="mainCard">
        <h1>Gaming Timer</h1>

        <!-- Settings Panel Overlay -->
        <div class="settings-panel" id="settingsPanel">
            <!-- Back Button -->
            <button class="btn-secondary" onclick="toggleSettings()" style="margin-top:0; margin-bottom: 20px; width: auto; align-self: flex-start; padding: 10px 20px; font-size: 0.9rem;">
                ← Back
            </button>
            
            <h2 style="font-family: 'Oswald'; margin-bottom: 10px;">Visual Settings</h2>

            <div class="setting-item" style="flex-direction: column; align-items: flex-start; gap: 5px;">
                <label>Clock Font Style</label>
                <select id="configFont" onchange="handleFontSelection()" style="width: 100%; padding: 10px; font-size: 0.9rem;">
                    <optgroup label="Default Built-in">
                        <option value="'Share Tech Mono', monospace">7-Segment (Tech)</option>
                        <option value="'Oswald', sans-serif">Oswald (Bold)</option>
                        <option value="'Outfit', sans-serif">Outfit (Clean)</option>
                    </optgroup>
                    
                    <?php if (count($server_fonts) > 0): ?>
                    <optgroup label="Server Fonts">
                        <?php foreach ($server_fonts as $font_file): 
                            $font_name = pathinfo($font_file, PATHINFO_FILENAME);
                        ?>
                        <option value="'<?php echo $font_name; ?>'"><?php echo $font_name; ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                    <?php endif; ?>
                    
                    <optgroup label="System / Custom">
                        <option value="custom">User Custom Font (Type name)</option>
                    </optgroup>
                </select>
                <input type="text" id="configCustomFont" placeholder="e.g. 'Meiryo', sans-serif" style="display: none; width: 100%; padding: 10px; font-size: 0.9rem; border-radius: 8px; border: 1px solid rgba(255,255,255,0.2); background: rgba(0,0,0,0.3); color: white;" oninput="applyVisualSettings()">
            </div>

            <div class="setting-item">
                <label>Font Size</label>
                <input type="range" class="range-slider" id="configFontSize" min="2" max="8" step="0.5" value="4" oninput="applyVisualSettings()">
            </div>

            <div class="setting-item">
                <label>Clock Text Color</label>
                <input type="color" class="color-picker" id="configColorText" value="#f8fafc" oninput="applyVisualSettings()">
            </div>
            
            <div class="setting-item">
                <label>Background Color</label>
                <input type="color" class="color-picker" id="configColorBg" value="#0f172a" oninput="applyVisualSettings()">
            </div>
        </div>

        <!-- Setup Form -->
        <div id="setupForm">
            <div class="time-form">
                <div class="input-group">
                    <label for="templateSelect">Game Template</label>
                    <select id="templateSelect" onchange="handleTemplateChange()">
                        <!-- Options populated by JS -->
                    </select>
                </div>

                <div class="input-group" id="manualTimeGroup">
                    <label for="gameTimeInput">Current In-Game Time</label>
                    <input type="time" id="gameTimeInput" required value="12:00">
                </div>
            </div>
            
            <div style="display: flex; gap: 10px;">
                <button onclick="startClock()" style="flex: 2;">Start Clock</button>
                <button onclick="toggleSettings()" class="btn-secondary" style="flex: 1; padding: 16px;">⚙️ UI</button>
            </div>

            <div class="help-text" id="helpText">
                Select a game to sync its specific time progression.
            </div>
        </div>

        <!-- Active Clock Display -->
        <div class="clock-container" id="clockContainer">
            <div id="gameNameDisplay">Game Name</div>
            
            <div class="clock-ring">
                <svg width="200" height="200">
                    <circle class="ring-bg" cx="100" cy="100" r="90"></circle>
                    <circle class="ring-progress" cx="100" cy="100" r="90" id="progressRing"></circle>
                </svg>
                <div class="ring-inner">
                    <div class="icon" id="timeIcon">☀️</div>
                </div>
            </div>

            <div class="digital-clock" id="digitalClock">
                00<span class="colon">:</span>00<span class="colon">:</span>00
            </div>
            <div class="time-period" id="timePeriod">DAY</div>

            <div style="display: flex; gap: 10px; margin-top: 30px;">
                <button class="btn-secondary" onclick="resetClock()" style="width: 100%; margin:0;">Stop Timer</button>
                <button class="btn-secondary" onclick="copyShareUrl()" style="width: 100%; margin:0;" id="copyUrlBtn">🔗 Copy URL</button>
                <button class="btn-secondary" onclick="toggleSettings()" style="width: auto; margin:0; padding:10px 20px;">⚙️</button>
            </div>
        </div>

    </div>

    <script>
        // --- GAME TEMPLATES ---
        const GAME_TEMPLATES = <?php echo json_encode($game_templates); ?>;

        // --- DOM Elements ---
        const rootStyles = document.documentElement.style;
        const setupForm = document.getElementById('setupForm');
        const clockContainer = document.getElementById('clockContainer');
        const settingsPanel = document.getElementById('settingsPanel');
        
        const templateSelect = document.getElementById('templateSelect');
        const manualTimeGroup = document.getElementById('manualTimeGroup');
        const gameTimeInput = document.getElementById('gameTimeInput');
        const helpText = document.getElementById('helpText');
        
        const clockEl = document.getElementById('digitalClock');
        const ringEl = document.getElementById('progressRing');
        const iconEl = document.getElementById('timeIcon');
        const periodEl = document.getElementById('timePeriod');
        const gameNameDisplay = document.getElementById('gameNameDisplay');

        // --- State ---
        let currentTemplateKey = 'hunter';
        let clockInterval = null;
        let sessionBaseRealTimeMs = 0;
        let sessionBaseGameTimeMs = 0;

        // --- Initialization ---
        function init() {
            for (const [key, tpl] of Object.entries(GAME_TEMPLATES)) {
                let opt = document.createElement('option');
                opt.value = key;
                opt.textContent = tpl.name;
                templateSelect.appendChild(opt);
            }
            handleTemplateChange();

            // Check URL parameters
            const params = new URLSearchParams(window.location.search);
            const gameQuery = params.get('game');
            const timeQuery = params.get('time');
            
            // UI Parameters
            const urlFont = params.get('font');
            const urlCustomFont = params.get('customFont');
            const urlSize = params.get('size');
            const urlColorText = params.get('colorText');
            const urlColorBg = params.get('colorBg');
            
            let autoStartMatched = false;

            // Apply Visual Settings (URL params take priority over Cookies)
            loadVisualSettings(urlFont, urlCustomFont, urlSize, urlColorText, urlColorBg);

            if (gameQuery) {
                // Try to find exact key first, then substring match on name
                let matchedKey = null;
                if (GAME_TEMPLATES[gameQuery]) {
                    matchedKey = gameQuery;
                } else {
                    const searchStr = gameQuery.toLowerCase();
                    for (const [key, tpl] of Object.entries(GAME_TEMPLATES)) {
                        if (tpl.name.toLowerCase().includes(searchStr)) {
                            matchedKey = key;
                            break;
                        }
                    }
                }

                if (matchedKey) {
                    templateSelect.value = matchedKey;
                    handleTemplateChange();
                    autoStartMatched = true;
                }
            }

            if (timeQuery && autoStartMatched) {
                // simple validation for HH:MM
                if (/^\d{1,2}:\d{2}$/.test(timeQuery)) {
                    // standardize format to 00:00
                    const parts = timeQuery.split(':');
                    const hh = parts[0].padStart(2, '0');
                    const mm = parts[1];
                    gameTimeInput.value = `${hh}:${mm}`;
                }
            }

            if (autoStartMatched) {
                startClock();
            }
        }

        function handleTemplateChange() {
            currentTemplateKey = templateSelect.value;
            const tpl = GAME_TEMPLATES[currentTemplateKey];
            helpText.textContent = tpl.desc;

            // Only show the manual time input if the mode is 'session'
            if (tpl.mode === 'session') {
                manualTimeGroup.style.display = 'flex';
            } else {
                manualTimeGroup.style.display = 'none';
            }
        }

        function startClock() {
            const tpl = GAME_TEMPLATES[currentTemplateKey];
            gameNameDisplay.textContent = tpl.name;

            if (tpl.mode === 'session') {
                const parts = gameTimeInput.value.split(':');
                if (parts.length !== 2) return;
                // Convert input HM to milliseconds since midnight
                sessionBaseGameTimeMs = (parseInt(parts[0]) * 3600 + parseInt(parts[1]) * 60) * 1000;
                sessionBaseRealTimeMs = Date.now();
            }

            setupForm.style.display = 'none';
            clockContainer.style.display = 'flex';

            updateClock();
            clockInterval = setInterval(updateClock, 1000); // 1 tick per real second
        }

        function resetClock() {
            clearInterval(clockInterval);
            clockContainer.style.display = 'none';
            setupForm.style.display = 'block';
        }

        function copyShareUrl() {
            const baseUrl = window.location.origin + window.location.pathname;
            const params = new URLSearchParams();
            
            // Add Game & Time
            params.set('game', currentTemplateKey);
            
            // For session mode, we copy the original start time used to bootstrap
            if (GAME_TEMPLATES[currentTemplateKey].mode === 'session') {
                 params.set('time', gameTimeInput.value);
            }

            // Add UI settings
            params.set('font', document.getElementById('configFont').value);
            if (document.getElementById('configFont').value === 'custom') {
                params.set('customFont', document.getElementById('configCustomFont').value);
            }
            params.set('size', document.getElementById('configFontSize').value);
            params.set('colorText', document.getElementById('configColorText').value);
            params.set('colorBg', document.getElementById('configColorBg').value);

            const finalUrl = baseUrl + '?' + params.toString();

            navigator.clipboard.writeText(finalUrl).then(() => {
                const btn = document.getElementById('copyUrlBtn');
                const origText = btn.innerHTML;
                btn.innerHTML = '✅ Copied!';
                setTimeout(() => { btn.innerHTML = origText; }, 2000);
            }).catch(err => {
                console.error('Failed to copy: ', err);
                alert("URL Copy failed. Your browser might block clipboard access here.");
            });
        }

        function setCookie(name, value, days = 365) {
            const d = new Date();
            d.setTime(d.getTime() + (days * 24 * 60 * 60 * 1000));
            document.cookie = name + "=" + encodeURIComponent(value) + ";expires=" + d.toUTCString() + ";path=/";
        }

        function getCookie(name) {
            const nameEQ = name + "=";
            const ca = document.cookie.split(';');
            for(let i = 0; i < ca.length; i++) {
                let c = ca[i];
                while (c.charAt(0) == ' ') c = c.substring(1, c.length);
                if (c.indexOf(nameEQ) == 0) return decodeURIComponent(c.substring(nameEQ.length, c.length));
            }
            return null;
        }

        // --- Settings Panel Logic ---
        function toggleSettings() {
            settingsPanel.classList.toggle('active');
        }

        function handleFontSelection() {
            const fontSelect = document.getElementById('configFont');
            const customInput = document.getElementById('configCustomFont');
            
            if (fontSelect.value === 'custom') {
                customInput.style.display = 'block';
            } else {
                customInput.style.display = 'none';
            }
            applyVisualSettings();
        }

        function applyVisualSettings() {
            const fontSelectValue = document.getElementById('configFont').value;
            let font = "";
            let customVal = "";

            if (fontSelectValue === 'custom') {
                customVal = document.getElementById('configCustomFont').value;
                font = customVal || "'Share Tech Mono', monospace"; // fallback if empty
            } else {
                font = fontSelectValue;
            }

            const size = document.getElementById('configFontSize').value;
            const sizeStr = size + 'rem';
            const color = document.getElementById('configColorText').value;
            const bg = document.getElementById('configColorBg').value;

            // Apply to CSS Variables
            rootStyles.setProperty('--clock-font', font);
            rootStyles.setProperty('--clock-size', sizeStr);
            rootStyles.setProperty('--clock-color', color);
            rootStyles.setProperty('--bg-color', bg);

            // Save to Cookies
            setCookie('gt_font_select', fontSelectValue);
            setCookie('gt_font_custom', customVal);
            setCookie('gt_font_size', size);
            setCookie('gt_color', color);
            setCookie('gt_bg', bg);
        }

        function loadVisualSettings(urlFont, urlCustomFont, urlSize, urlColorText, urlColorBg) {
            // Priority: URL Param > Cookie > Default
            const savedFontSelect = urlFont || getCookie('gt_font_select');
            const savedFontCustom = urlCustomFont || getCookie('gt_font_custom');
            const savedSize = urlSize || getCookie('gt_font_size');
            const savedColor = urlColorText || getCookie('gt_color');
            const savedBg = urlColorBg || getCookie('gt_bg');

            if (savedFontSelect) {
                const selectEl = document.getElementById('configFont');
                // Check if option exists, otherwise fall back to custom or default
                let optionExists = false;
                for (let i = 0; i < selectEl.options.length; i++) {
                    if (selectEl.options[i].value === savedFontSelect) {
                        optionExists = true; break;
                    }
                }
                
                if (optionExists || savedFontSelect === 'custom') {
                    selectEl.value = savedFontSelect;
                } else {
                    selectEl.value = "'Share Tech Mono', monospace";
                }

                if (savedFontSelect === 'custom') {
                    document.getElementById('configCustomFont').style.display = 'block';
                    if (savedFontCustom) {
                        document.getElementById('configCustomFont').value = savedFontCustom;
                        rootStyles.setProperty('--clock-font', savedFontCustom);
                    }
                } else {
                    rootStyles.setProperty('--clock-font', savedFontSelect);
                }
            }
            if (savedSize) {
                document.getElementById('configFontSize').value = parseFloat(savedSize);
                rootStyles.setProperty('--clock-size', savedSize + 'rem');
            }
            if (savedColor) {
                document.getElementById('configColorText').value = savedColor;
                rootStyles.setProperty('--clock-color', savedColor);
            }
            if (savedBg) {
                document.getElementById('configColorBg').value = savedBg;
                rootStyles.setProperty('--bg-color', savedBg);
            }
        }

        // --- Core Time Math ---
        function getGameTimeMs() {
            const now = Date.now();
            const tpl = GAME_TEMPLATES[currentTemplateKey];

            if (tpl.mode === 'session') {
                // How many real MS have passed since they clicked Start?
                const elapsedRealMs = now - sessionBaseRealTimeMs;
                // Multiplier: e.g. 15 real seconds = 60 game seconds -> factor is 4.0
                const speedMultiplier = 60 / tpl.realSecondsPerGameMinute;
                
                let gameTimeMs = sessionBaseGameTimeMs + (elapsedRealMs * speedMultiplier);
                return gameTimeMs % (24 * 3600 * 1000);

            } else if (tpl.mode === 'realtime') {
                // Exactly matches real world clock
                const d = new Date(now);
                return (d.getHours() * 3600 + d.getMinutes() * 60 + d.getSeconds()) * 1000 + d.getMilliseconds();

            } else if (tpl.mode === 'rolc') {
                // ROLC 120-minute cycle mapping:
                // TimeZone = ( real_hours * 60 ) + ( real_minutes + 120 )
                // TimeZone = fmod( TimeZone, 120 )
                // This means every 120 real minutes = 24 game hours.
                const d = new Date(now);
                // Total real minutes today mapping to the 120 min window
                const realMinutes = (d.getHours() * 60) + d.getMinutes();
                const timeZoneMin = realMinutes % 120; // 0 to 119 minutes
                
                // Map the 120 real minutes onto a 24 * 60 (1440) game minutes scale
                // 1 real minute = 12 game minutes
                const gameMinutes = timeZoneMin * 12;
                // Add seconds proportionally (1 real second = 12 game seconds)
                const gameSeconds = d.getSeconds() * 12;
                
                return ((gameMinutes * 60) + gameSeconds) * 1000;

            } else if (tpl.mode === 'persistent') {
                // e.g. 70 real minutes = 1 game day (24 hours)
                // Ratio: 1440 game minutes per X real minutes
                const speedMultiplier = (24 * 60) / tpl.realMinutesPerGameDay;
                return (now * speedMultiplier) % (24 * 3600 * 1000);
            }
            return 0;
        }

        function updateClock() {
            const tpl = GAME_TEMPLATES[currentTemplateKey];
            const ms = getGameTimeMs();
            
            // Convert MS to H:M:S
            const totalSeconds = Math.floor(ms / 1000);
            const h = Math.floor(totalSeconds / 3600);
            const m = Math.floor((totalSeconds % 3600) / 60);
            const s = totalSeconds % 60;

            const hStr = h.toString().padStart(2, '0');
            const mStr = m.toString().padStart(2, '0');
            const sStr = s.toString().padStart(2, '0');
            
            // Format HH:MM:SS
            clockEl.innerHTML = `${hStr}<span class="colon">:</span>${mStr}<span class="colon">:</span>${sStr}`;

            // Phase Logic
            let gameHourFloat = h + (m / 60) + (s / 3600);
            let activePhase = tpl.phases[tpl.phases.length - 1]; // fallback to last
            
            for (let i = 0; i < tpl.phases.length; i++) {
                if (gameHourFloat >= tpl.phases[i].start) {
                    activePhase = tpl.phases[i];
                }
            }

            // Apply Theme
            if (periodEl.textContent !== activePhase.name) {
                iconEl.innerText = activePhase.icon;
                periodEl.innerText = activePhase.name;
                periodEl.style.color = activePhase.color;
                document.documentElement.style.setProperty('--primary-color', activePhase.color);
            }

            // Ring Progress: cycles once every in-game hour
            // E.g. 15 real seconds = 60 game seconds. It takes 15 real minutes for the circle to complete.
            const circumference = 2 * Math.PI * 90; // 565.48
            const percentOfHour = (m * 60 + s) / 3600; 
            const offset = Math.max(0, circumference - (percentOfHour * circumference));
            ringEl.style.strokeDashoffset = offset;
        }

        // Boot
        init();

    </script>
</body>
</html>
