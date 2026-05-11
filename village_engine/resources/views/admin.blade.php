<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>The Village - Admin Dashboard</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <style>
        body {
            background: #0f0f23;
            color: white;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 0;
            padding: 20px;
        }
        .admin-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .header {
            text-align: center;
            margin-bottom: 40px;
        }
        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 40px;
        }
        .card {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            padding: 20px;
        }
        .card h2 {
            margin-top: 0;
            color: #00ff88;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 4px;
            color: white;
            font-size: 14px;
        }
        .btn {
            background: #00ff88;
            color: #0f0f23;
            border: none;
            padding: 12px 24px;
            border-radius: 4px;
            font-weight: bold;
            cursor: pointer;
            font-size: 14px;
        }
        .btn:hover {
            background: #00cc6a;
        }
        .btn-secondary {
            background: #666;
            color: white;
        }
        .btn-secondary:hover {
            background: #555;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        .stat-item {
            background: rgba(255, 255, 255, 0.05);
            padding: 15px;
            border-radius: 4px;
            text-align: center;
        }
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #00ff88;
        }
        .stat-label {
            font-size: 12px;
            opacity: 0.7;
        }
        .village-preview {
            width: 100%;
            height: 300px;
            background: #1a1a2e;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 4px;
            position: relative;
        }
        .success-message {
            background: rgba(0, 255, 136, 0.2);
            border: 1px solid #00ff88;
            color: #00ff88;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="header">
            <h1>The Village - Admin Dashboard</h1>
            <p>Create and manage your village server</p>
        </div>

        @if(session('success'))
            <div class="success-message">
                {{ session('success') }}
            </div>
        @endif

        <div class="dashboard-grid">
            <!-- Server Configuration -->
            <div class="card">
                <h2>🏘️ Server Configuration</h2>
                <form method="POST" action="{{ route('admin.store-server') }}">
                    @csrf
                    <div class="form-group">
                        <label for="server_name">Server Name</label>
                        <input type="text" id="server_name" name="server_name" required>
                    </div>
                    <div class="form-group">
                        <label for="server_domain">Server Domain</label>
                        <input type="text" id="server_domain" name="server_domain" placeholder="village.example.com" required>
                    </div>
                    <div class="form-group">
                        <label for="max_population">Max Population</label>
                        <input type="number" id="max_population" name="max_population" value="1000" min="10" max="10000">
                    </div>
                    <div class="form-group">
                        <label for="server_description">Description</label>
                        <textarea id="server_description" name="server_description" rows="3"></textarea>
                    </div>
                    <button type="submit" class="btn">Create Server</button>
                </form>
            </div>

            <!-- Village Creation -->
            <div class="card">
                <h2>🏗️ Village Design</h2>
                <form method="POST" action="{{ route('admin.create-village') }}">
                    @csrf
                    <div class="form-group">
                        <label for="village_name">Village Name</label>
                        <input type="text" id="village_name" name="village_name" required>
                    </div>
                    <div class="form-group">
                        <label for="village_type">Village Type</label>
                        <select id="village_type" name="village_type">
                            <option value="tech">Tech Hub</option>
                            <option value="art">Art District</option>
                            <option value="science">Science Park</option>
                            <option value="residential">Residential Area</option>
                            <option value="commercial">Commercial District</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="village_size">Village Size</label>
                        <select id="village_size" name="village_size">
                            <option value="small">Small (100-500 users)</option>
                            <option value="medium">Medium (500-2000 users)</option>
                            <option value="large">Large (2000-10000 users)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="reputation_required">Minimum Reputation</label>
                        <input type="number" id="reputation_required" name="reputation_required" value="50" min="0" max="100">
                    </div>
                    <button type="submit" class="btn">Create Village</button>
                </form>
            </div>
        </div>

        <!-- Network Statistics -->
        <div class="card">
            <h2>📊 Network Statistics</h2>
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-value">{{ $totalUsers ?? 0 }}</div>
                    <div class="stat-label">Total Users</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">{{ $totalVillages ?? 0 }}</div>
                    <div class="stat-label">Total Villages</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">{{ $activeUsers ?? 0 }}</div>
                    <div class="stat-label">Active Now</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">{{ $serverUptime ?? '0h' }}</div>
                    <div class="stat-label">Server Uptime</div>
                </div>
            </div>
        </div>

        <!-- 3D Village Preview -->
        <div class="card">
            <h2>🎮 3D Preview</h2>
            <div class="village-preview" id="village-preview">
                <canvas id="admin-canvas"></canvas>
            </div>
            <div style="margin-top: 15px;">
                <button class="btn btn-secondary" onclick="previewVillage()">Preview Village</button>
                <button class="btn" onclick="saveVillage()">Save Configuration</button>
            </div>
        </div>
    </div>

    <script>
        // Simple 3D preview for admin dashboard
        function previewVillage() {
            const canvas = document.getElementById('admin-canvas');
            const ctx = canvas.getContext('2d');
            
            // Set canvas size
            canvas.width = canvas.offsetWidth;
            canvas.height = canvas.offsetHeight;
            
            // Clear canvas
            ctx.fillStyle = '#1a1a2e';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            
            // Draw sample village layout
            const centerX = canvas.width / 2;
            const centerY = canvas.height / 2;
            
            // Draw village boundary
            ctx.strokeStyle = '#00ff88';
            ctx.lineWidth = 2;
            ctx.beginPath();
            ctx.arc(centerX, centerY, 100, 0, Math.PI * 2);
            ctx.stroke();
            
            // Draw sample buildings
            const buildings = [
                { x: -50, y: -30, w: 30, h: 40, color: '#8B4513' },
                { x: 20, y: 10, w: 25, h: 35, color: '#FF6B6B' },
                { x: -20, y: 40, w: 35, h: 30, color: '#4ECDC4' },
                { x: 60, y: -20, w: 28, h: 45, color: '#9333EA' }
            ];
            
            buildings.forEach(building => {
                ctx.fillStyle = building.color;
                ctx.fillRect(centerX + building.x, centerY + building.y, building.w, building.h);
                
                // Draw roof
                ctx.fillStyle = '#FF6B6B';
                ctx.beginPath();
                ctx.moveTo(centerX + building.x - 5, centerY + building.y);
                ctx.lineTo(centerX + building.x + building.w/2, centerY + building.y - 15);
                ctx.lineTo(centerX + building.x + building.w + 5, centerY + building.y);
                ctx.closePath();
                ctx.fill();
            });
            
            // Draw village name
            ctx.fillStyle = '#ffffff';
            ctx.font = 'bold 16px Arial';
            ctx.textAlign = 'center';
            ctx.fillText('Preview Village', centerX, centerY - 120);
        }
        
        // Initialize preview on load
        window.addEventListener('load', previewVillage);
        
        // Resize preview on window resize
        window.addEventListener('resize', previewVillage);
        
        function saveVillage() {
            alert('Village configuration saved! (Integration with backend needed)');
        }
    </script>
</body>
</html>
