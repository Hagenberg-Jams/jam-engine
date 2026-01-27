<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $gameName }} - {{ $year }}</title>
    <style>
        @page {
            size: A4;
            margin: 20mm;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11pt;
            line-height: 1.6;
            color: #333;
            margin: 20mm;
        }

        .header {
            border-bottom: 3px solid #5956eb;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }

        .title {
            font-size: 24pt;
            font-weight: bold;
            color: #1e293b;
            margin-bottom: 5px;
        }

        .year {
            font-size: 14pt;
            color: #64748b;
            margin-bottom: 10px;
        }

        .team-name {
            font-size: 16pt;
            font-weight: bold;
            color: #5956eb;
            margin-top: 10px;
            font-family: Arial, Helvetica, sans-serif;
        }

        .section {
            margin-bottom: 15px;
        }

        /* Keep sections (esp. screenshots) together on one page where possible */
        .screenshots-section {
            page-break-inside: avoid;
        }

        .section-title {
            font-size: 14pt;
            font-weight: bold;
            color: #1e293b;
            margin-bottom: 8px;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 3px;
        }

        .description {
            text-align: justify;
            margin-bottom: 10px;
        }

        .controls {
            background-color: #f8fafc;
            padding: 10px;
            border-left: 4px solid #5956eb;
            font-family: 'Courier New', monospace;
            font-size: 10pt;
            white-space: pre-wrap;
            margin-bottom: 10px;
        }

        .team-members {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 8px;
        }

        .member {
            background-color: #e2e8f0;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 10pt;
            white-space: nowrap;
        }

        .info-box {
            display: inline-block;
            background-color: #f1f5f9;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 10pt;
            margin-right: 10px;
            margin-bottom: 10px;
        }

        .screenshots {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-top: 10px;
        }

        .screenshot {
            flex: 1;
            min-width: 150px;
            max-width: 48%;
        }

        .screenshot img {
            width: 100%;
            height: auto;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
        }

        .header-image {
            width: 100%;
            max-height: 150px;
            object-fit: cover;
            border-radius: 4px;
            margin-bottom: 15px;
        }

        .footer {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #e2e8f0;
            font-size: 9pt;
            color: #64748b;
            text-align: center;
        }
    </style>
</head>
<body>
    @php
        $logoPath = base_path('_media/hagenberg_game_jam_logo_black.svg');
    @endphp
    @if(file_exists($logoPath))
        <div style="text-align: center; margin-bottom: 15px;">
            <img src="{{ $logoPath }}" alt="Hagenberg Game Jam" style="max-height: 40px; width: auto;">
        </div>
    @endif
    
    <div class="header">
        <div class="title">{{ $gameName }}</div>
        <div class="year">Hagenberg Game Jam {{ $year }}</div>
        @if(!empty($teamName))
            <div class="team-name">{{ $teamName }}</div>
        @endif
    </div>

    @if(!empty($headerImagePath) && file_exists($headerImagePath))
        <img src="{{ $headerImagePath }}" alt="{{ $gameName }}" class="header-image">
    @endif

    <div style="display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 15px;">
        <div class="info-box">
            <strong>Players:</strong>
            @if (preg_match('/^(\d+)-(\d+)$/', (string) $players, $matches))
                {{ $matches[1] }}–{{ $matches[2] }}
            @else
                {{ (int) $players }} {{ (int) $players === 1 ? 'Player' : 'Players' }}
            @endif
        </div>
        
        @if(!empty($inputMethods))
            <div class="info-box">
                <strong>Input:</strong> {{ $inputMethods }}
            </div>
        @endif
        
        @if(!empty($platforms))
            <div class="info-box">
                <strong>Platforms:</strong> {{ $platforms }}
            </div>
        @endif
    </div>

    @if(!empty($teamMembers))
        <div class="section">
            <div class="section-title">Team Members</div>
            <div class="team-members">
                @foreach($teamMembers as $member)
                    <span class="member">{{ $member }}</span>
                @endforeach
            </div>
        </div>
    @endif

    @if(!empty($description))
        <div class="section">
            <div class="section-title">About the Game</div>
            <div class="description">
                @php
                    // Split by double line breaks (paragraphs)
                    $paragraphs = preg_split('/\n\s*\n/', trim($description));
                @endphp
                @foreach($paragraphs as $paragraph)
                    @php
                        // Convert markdown links [text](url) to HTML links
                        $paragraphWithLinks = preg_replace_callback(
                            '/\[([^\]]+)\]\(([^)]+)\)/',
                            function ($matches) {
                                $text = htmlspecialchars($matches[1], ENT_QUOTES, 'UTF-8');
                                $url = htmlspecialchars($matches[2], ENT_QUOTES, 'UTF-8');
                                return '<a href="' . $url . '" style="color: #5956eb; text-decoration: underline;">' . $text . '</a>';
                            },
                            trim($paragraph)
                        );
                    @endphp
                    <p>{!! $paragraphWithLinks !!}</p>
                @endforeach
            </div>
        </div>
    @endif

    @if(!empty($controlsText))
        <div class="section">
            <div class="section-title">Controls</div>
            <div class="controls">{{ $controlsText }}</div>
        </div>
    @endif

    @if(!empty($imagePaths))
        <div class="section screenshots-section">
            <div class="section-title">Screenshots</div>
            <div class="screenshots">
                @foreach($imagePaths as $imagePath)
                    @if(file_exists($imagePath))
                        <div class="screenshot">
                            <img src="{{ $imagePath }}" alt="Screenshot">
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    @endif

    <div class="footer">
        Generated for Hagenberg Game Jam {{ $year }} | hagenberg-gamejam.at
    </div>
</body>
</html>
