<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>API Unreachable - Amusement Club</title>
        
        <!-- Phosphor Icons -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.2/src/bold/style.css" integrity="sha384-nblAP2mo2pVPyMQZDw9Xy9Cwgs9lowushAYep4w5+Q9kF4Ibf3n0B/gCMVdR+Vqy" crossorigin="anonymous">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.2/src/duotone/style.css" integrity="sha384-UVFvhZP7fWAENUPOoq61rJg5ef6QZW0k5vpAn71041/QS4STvUcJQCGNhStZLGx9" crossorigin="anonymous">
        
        <!-- Application Styles -->
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
                transition: background-color 0.2s;
            }
            .error-container {
                background: var(--glass-bg);
                border: 1px solid var(--glass-border);
                border-radius: 16px;
                padding: 3rem;
                max-width: 500px;
                backdrop-filter: blur(12px);
                box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
                animation: floatIn 0.3s ease-out;
                position: relative;
            }
            @keyframes floatIn {
                from { opacity: 0; transform: scale(0.95); }
                to { opacity: 1; transform: scale(1); }
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
            .close-btn {
                position: absolute;
                top: 1rem;
                right: 1rem;
                background: transparent;
                border: none;
                color: var(--text-secondary);
                font-size: 1.5rem;
                cursor: pointer;
                transition: color 0.2s;
                display: none; /* hidden by default, shown if in iframe */
            }
            .close-btn:hover {
                color: white;
            }
        </style>
    </head>
    <body>
        <div class="error-container">
            <button id="close-x-btn" class="close-btn">
                <i class="ph-bold ph-x"></i>
            </button>
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
            <button id="go-back-btn" class="btn" style="background: rgba(255,255,255,0.1); margin-left: 0.5rem; border: 1px solid var(--glass-border);">
                Go Back
            </button>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const goBackBtn = document.getElementById('go-back-btn');
                const closeXBtn = document.getElementById('close-x-btn');
                
                // If we are inside an iframe (e.g. Livewire's error modal)
                if (window.parent !== window) {
                    // Make body transparent so the parent backdrop shows through
                    document.body.style.backgroundColor = 'transparent';
                    
                    // Show the 'X' button
                    closeXBtn.style.display = 'block';
                    
                    // Attempt to style the parent iframe wrapper to be fully transparent/borderless
                    try {
                        const parentDoc = window.parent.document;
                        const lwError = parentDoc.getElementById('livewire-error');
                        
                        if (lwError) {
                            // Strip inline styles set by Livewire
                            lwError.style.cssText = `
                                z-index: 999999 !important;
                                margin: 0 !important;
                                padding: 0 !important;
                                width: 100% !important;
                                height: 100% !important;
                                max-width: 100% !important;
                                max-height: 100% !important;
                                border: none !important;
                                background: transparent !important;
                            `;
                            
                            // The iframe is inside the dialog
                            const iframe = lwError.querySelector('iframe');
                            if (iframe) {
                                iframe.style.cssText = `
                                    width: 100% !important;
                                    height: 100% !important;
                                    border: none !important;
                                    background: transparent !important;
                                    border-radius: 0 !important;
                                `;
                            }
                        }
                        
                        // Also inject a style block just in case
                        const style = parentDoc.createElement('style');
                        style.innerHTML = `
                            dialog#livewire-error::backdrop {
                                background: rgba(0,0,0,0.8) !important;
                            }
                        `;
                        parentDoc.head.appendChild(style);
                        
                    } catch(e) {
                        console.error('Could not style parent document', e);
                    }
                    
                    // Function to dismiss the Livewire modal
                    const dismissModal = function(e) {
                        e.preventDefault();
                        try {
                            const parentDoc = window.parent.document;
                            const lwError = parentDoc.getElementById('livewire-error');
                            if (lwError) {
                                lwError.remove();
                                parentDoc.body.style.overflow = '';
                            }
                        } catch(e) {
                            console.error('Could not remove modal', e);
                        }
                    };
                    
                    goBackBtn.innerHTML = 'Dismiss';
                    goBackBtn.onclick = dismissModal;
                    closeXBtn.onclick = dismissModal;
                    
                } else {
                    // Standard page load behavior
                    goBackBtn.onclick = function(e) {
                        e.preventDefault();
                        window.history.back();
                    };
                }
            });
        </script>
    </body>
</html>
