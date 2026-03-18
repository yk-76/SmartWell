<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NutriScan Food Journal</title>
    <link rel="shortcut icon" href="image/SmartWell_logo_Only.png" />
    
    <!-- Bootstrap CSS 5.0 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    
    <!-- Google Fonts -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Montserrat:300,400,500,600,700&display=swap">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/style.css">
    
    <!-- Fallback styles in case external CSS fails to load -->
    <style>
        /* Fallback styles for journal */
        .journal-container {
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
            background-color: #f8f9fa;
            min-height: 100vh;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .stat-card {
            background-color: #fff3e0;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }

        .stat-value {
            font-size: 32px;
            font-weight: bold;
            color: #4CAF50;
            margin: 10px 0;
        }

        .stat-label {
            color: #fb8c00;
            font-size: 16px;
            font-weight: 500;
        }

        .stat-description {
            color: #666;
            font-size: 14px;
        }

        .tabs {
            display: flex;
            border-bottom: 1px solid #ddd;
            margin-bottom: 20px;
            background-color: white;
            border-radius: 8px 8px 0 0;
        }

        .tab {
            padding: 12px 24px;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            font-weight: 500;
            transition: all 0.2s ease;
            background-color: white;
        }

        .tab:hover {
            background-color: #f5f5f5;
        }

        .tab.active {
            border-bottom: 2px solid #4CAF50;
            font-weight: bold;
            color: #4CAF50;
        }

        .tab-content {
            display: none;
            background-color: white;
            border-radius: 0 0 8px 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .tab-content.active {
            display: block;
        }

        .entries-container {
            padding: 20px;
        }

        .entries-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .entry-card {
            display: flex;
            border-bottom: 1px solid #eee;
            padding: 15px 0;
        }

        .entry-image {
            width: 80px;
            height: 80px;
            border-radius: 4px;
            object-fit: cover;
        }

        .entry-content {
            flex: 1;
            margin-left: 15px;
        }

        .entry-title {
            font-weight: bold;
            margin-bottom: 5px;
            font-size: 16px;
        }

        .entry-date {
            color: #666;
            font-size: 12px;
            margin-bottom: 8px;
        }

        .entry-advice {
            font-size: 14px;
            color: #333;
            line-height: 1.5;
        }

        .healthy {
            color: #4CAF50;
        }

        .unhealthy {
            color: #f44336;
        }

        .journal-btn {
            padding: 0.6rem 1.2rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 120px;
            text-decoration: none;
            color: white;
        }

        .journal-btn-primary {
            background-color: #ff9800;
        }

        .journal-btn-primary:hover {
            background-color: #fb8c00;
            color: white;
        }

        .charts-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
            padding: 20px;
        }

        .chart-card {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .chart-title {
            font-weight: bold;
            margin-bottom: 15px;
            color: #333;
            text-align: center;
        }

        .insights-panel {
            background-color: #f0f7ff;
            border-radius: 8px;
            padding: 20px;
            margin: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .insights-title {
            color: #0066cc;
            font-weight: bold;
            margin-bottom: 15px;
            font-size: 18px;
        }

        .insights-list {
            list-style-type: none;
            padding: 0;
            margin: 0;
        }

        .insights-list li {
            padding: 8px 0;
            font-size: 15px;
            color: #333;
            line-height: 1.5;
            position: relative;
            padding-left: 20px;
        }

        .insights-list li:before {
            content: "•";
            color: #0066cc;
            position: absolute;
            left: 0;
            font-size: 18px;
            line-height: 1;
        }

        .journal-analytics-responsive {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            padding: 0 20px;
        }
        
        .journal-chart-card-responsive {
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
            padding: 20px 10px;
        }
        
        .journal-chart-canvas-wrapper {
            width: 100%;
            overflow-x: auto;
        }
        
        .journal-chart-canvas-wrapper canvas {
            width: 100% !important;
            max-width: 100vw;
            height: auto !important;
            display: block;
        }
        
        .journal-analytics-responsive {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            padding: 0 20px;
        }
        .journal-chart-card-responsive {
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
            padding: 20px 10px;
        }
        .journal-chart-canvas-wrapper {
            width: 100%;
            aspect-ratio: 2 / 1; /* makes it nicely wide on desktop, auto on mobile */
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .journal-chart-canvas-wrapper canvas {
            width: 100% !important;
            height: 100% !important;
            max-width: 100%;
            max-height: 100%;
            display: block;
        }
        
        /* Mobile responsive */
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .charts-container {
                grid-template-columns: 1fr;
            }
            
            .entry-card {
                flex-direction: column;
            }
            
            .entry-image {
                width: 100%;
                height: auto;
                margin-bottom: 10px;
            }
            
            .entry-content {
                margin-left: 0;
            }
            
            .journal-analytics-responsive {
                grid-template-columns: 1fr;
                gap: 12px;
                padding: 0 4px;
            }
            .journal-chart-card-responsive {
                padding: 12px 0px;
                border-radius: 6px;
            }
            .journal-chart-canvas-wrapper {
                aspect-ratio: 1.8 / 1;  /* slightly taller for mobile */
            }
        }
    </style>
</head>
<body>
    <div class="journal-container">
        <a href="ProductDetection.php" class="journal-btn journal-btn-primary">← Back to Scanner</a>

        <h1 style="margin: 20px 0; color: #333;">Your Nutrition Journal</h1>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Nutrition Score</div>
                <div class="stat-value" id="healthScore">-</div>
                <div class="stat-description">Percentage of nutritious choices</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Items Tracked</div>
                <div class="stat-value" id="totalEntries">-</div>
                <div class="stat-description">Total foods analyzed</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Healthy Days</div>
                <div class="stat-value" id="healthyStreak">-</div>
                <div class="stat-description">Days with nutritious choices</div>
            </div>
        </div>
        
        <div class="tabs">
            <div class="tab active" data-tab="journal">Food Journal</div>
            <div class="tab" data-tab="analytics">Analytics</div>
        </div>
        
        <div class="tab-content active" id="journalTab">
            <div class="entries-container">
                <div class="entries-header">
                    <h2>Recent Food Entries</h2>
                    <button id="clearEntries" class="journal-btn journal-btn-primary">Clear History</button>
                </div>
                
                <div id="entriesList">
                    <p id="noEntries" style="text-align: center; color: #666;">No food entries yet. Start scanning foods to build your nutrition journal!</p>
                </div>
            </div>
        </div>
        
        <div class="tab-content" id="analyticsTab">
            <div class="charts-container journal-analytics-responsive">
                <div class="chart-card journal-chart-card-responsive">
                    <div class="chart-title">Nutrition Distribution</div>
                    <div class="journal-chart-canvas-wrapper">
                        <canvas id="healthDistributionChart"></canvas>
                    </div>
                </div>
                <div class="chart-card journal-chart-card-responsive">
                    <div class="chart-title">Weekly Tracking</div>
                    <div class="journal-chart-canvas-wrapper">
                        <canvas id="weeklyTrackingChart"></canvas>
                    </div>
                </div>
            </div>
            
            <div class="insights-panel">
                <div class="insights-title">Your Personal Nutrition Insights</div>
                <ul class="insights-list" id="insightsList">
                    <li>Start scanning foods to get personalized insights!</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle 5.0 -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
    
    <!-- Custom JavaScript -->
    <script src="Javascript/journal.js"></script>
    
</body>
</html>