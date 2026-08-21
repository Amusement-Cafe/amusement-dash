<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>API Unreachable - Amusement Club</title>
        
        <!-- Phosphor Icons -->
        <script src="https://unpkg.com/@phosphor-icons/web"></script>
        
        <!-- Application Styles (reuse existing variables if possible) -->
        <style>
            :root {
                --bg-color: #09090b;
                --text-color: #f8fafc;
                --text-secondary: #94a3b8;
                --accent-solid: #ec4899;
                --glass-bg: rgba(30, 41, 59, 0.5);
                --glass-border: rgba(255, 255, 255, 0.1);
            }
            body {
                margin: 0;
                padding: 0;
                font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                background-color: var(--bg-color);
                color: var(--text-color);
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
                text-align: center;
            }
            .error-container {
                background: var(--glass-bg);
                border: 1px solid var(--glass-border);
                border-radius: 16px;
                padding: 3rem;
                max-width: 500px;
                backdrop-filter: blur(12px);
                box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
                animation: floatIn 0.5s ease-out;
            }
            @keyframes floatIn {
                from { opacity: 0; transform: translateY(20px); }
                to { opacity: 1; transform: translateY(0); }
            }
            .icon-wrapper {
                color: #ef4444;
                font-size: 5rem;
                margin-bottom: 1rem;
            }
            h1 {
                margin: 0 0 1rem 0;
                font-size: 2rem;
            }
            p {
                color: var(--text-secondary);
                line-height: 1.6;
                margin-bottom: 2rem;
            }
            .btn {
                background: var(--accent-solid);
                color: white;
                border: none;
                padding: 0.8rem 1.5rem;
                border-radius: 8px;
                font-weight: bold;
                text-decoration: none;
                cursor: pointer;
                transition: opacity 0.2s;
                display: inline-block;
            }
            .btn:hover {
                opacity: 0.9;
            }
        </style>
    </head>
    <body>
        <div class="error-container">
            <div class="icon-wrapper">
                <i class="ph-duotone ph-warning-octagon"></i>
            </div>
            <h1>API Unreachable</h1>
            <p>
                The Amusement Club API is currently unavailable. This service is required to process your request. 
                Please try again in a few moments.
            </p>
            <button onclick="window.location.reload()" class="btn">
                <i class="ph-bold ph-arrows-clockwise" style="margin-right: 0.5rem; vertical-align: middle;"></i> Try Again
            </button>
            <button onclick="window.history.back()" class="btn" style="background: rgba(255,255,255,0.1); margin-left: 0.5rem; border: 1px solid var(--glass-border);">
                Go Back
            </button>
        </div>
    </body>
</html>
