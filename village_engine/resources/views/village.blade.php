<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>The Village - 3D P2P Trust Network</title>
    
    <!-- Meta tags for SEO and PWA -->
    <meta name="description" content="The Village - 3D P2P Trust Network with reputation-based communities">
    <meta name="keywords" content="p2p, trust network, 3d village, reputation, community">
    <meta name="author" content="The Village Team">
    
    <!-- PWA Configuration -->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
    <meta name="apple-mobile-web-app-title" content="The Village">
    <link rel="apple-touch-icon" href="/icons/Icon-192.png">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="/favicon.png">
    
    <!-- Tailwind CSS Production -->
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    
    <!-- Custom Styles -->
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            overflow: hidden;
        }
        
        #loading {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }
        
        .loading-spinner {
            width: 60px;
            height: 60px;
            border: 4px solid rgba(255,255,255,0.3);
            border-top: 4px solid #ffffff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        #village-canvas {
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .controls {
            position: fixed;
            top: 20px;
            left: 20px;
            background: rgba(0,0,0,0.7);
            padding: 15px;
            border-radius: 10px;
            color: white;
            z-index: 100;
            backdrop-filter: blur(10px);
        }
        
        .stats {
            position: fixed;
            top: 20px;
            right: 20px;
            background: rgba(0,0,0,0.7);
            padding: 15px;
            border-radius: 10px;
            color: white;
            z-index: 100;
            font-family: monospace;
            backdrop-filter: blur(10px);
        }
        
        .btn {
            background: #4f46e5;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            margin: 5px;
            transition: all 0.2s;
        }
        
        .btn:hover {
            background: #374151;
            transform: translateY(-1px);
        }
        
        .btn:disabled {
            background: #6b7280;
            cursor: not-allowed;
            transform: none;
        }
        
        .slider {
            width: 100%;
            margin: 10px 0;
        }
        
        .status-indicator {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 8px;
        }
        
        .status-online { background: #10b981; }
        .status-offline { background: #ef4444; }
        .status-connecting { background: #f59e0b; }
    </style>
</head>
<body>
    <!-- Loading Screen -->
    <div id="loading">
        <div style="text-align: center; color: white;">
            <div class="loading-spinner"></div>
            <h2 style="margin: 0; font-weight: 300;">The Village</h2>
            <p style="margin: 10px 0 0 0; opacity: 0.8;">Loading 3D Trust Network...</p>
        </div>
    </div>
    
    <!-- Main Application -->
    <div id="app" style="display: none;">
        <!-- 3D Canvas -->
        <canvas id="village-canvas"></canvas>
        
        <!-- Control Panel -->
        <div class="controls">
            <h3 class="text-lg font-bold mb-4">3D Village Map</h3>
            
            <!-- User Role Display -->
            <div class="mb-4 p-3 bg-gray-800 rounded">
                <div class="text-sm text-gray-400">Current Role</div>
                <div class="font-bold" id="current-role">Loading...</div>
                <div class="text-xs text-gray-500" id="current-user">Loading...</div>
                <div class="text-xs text-gray-500">Reputation: <span id="current-reputation">0</span></div>
            </div>
            
            <!-- Role Switching -->
            <div class="mb-4">
                <h4 class="text-sm font-medium mb-2">Switch Role:</h4>
                <select id="role-select" class="w-full p-2 bg-gray-700 rounded text-white">
                    <option value="">Select Role</option>
                    <option value="stranger">Stranger</option>
                    <option value="sojourner">Sojourner</option>
                    <option value="denizen">Denizen</option>
                    <option value="steward">Steward</option>
                    <option value="elder">Elder</option>
                    <option value="high_reeve">High Reeve</option>
                </select>
                <button id="switch-role-btn" class="btn mt-2">Switch Role</button>
            </div>
            
            <div class="space-y-2">
                <button id="toggle-3d" class="btn">Toggle 3D</button>
                <button id="toggle-separation" class="btn">Toggle 3° Separation</button>
                <button id="reset-camera" class="btn">Reset Camera</button>
                <button id="toggle-fullscreen" class="btn">Fullscreen</button>
            </div>
            
            <div class="mt-4">
                <label class="block text-sm font-medium mb-2">
                    Min Reputation: <span id="reputation-value">0</span>
                </label>
                <input type="range" id="reputation-slider" class="slider" min="0" max="100" value="0">
            </div>
            
            <div class="mt-4">
                <label class="block text-sm font-medium mb-2">
                    Node Density: <span id="density-value">50</span>
                </label>
                <input type="range" id="density-slider" class="slider" min="10" max="200" value="50">
            </div>
        </div>
        
        <!-- Statistics Panel -->
        <div class="stats">
            <h4 class="font-bold mb-2">Performance</h4>
            <div>FPS: <span id="fps">60</span></div>
            <div>Nodes: <span id="node-count">0</span></div>
            <div>Visible: <span id="visible-count">0</span></div>
            <div>Connections: <span id="connection-count">0</span></div>
            
            <div class="mt-4">
                <h4 class="font-bold mb-2">Network Status</h4>
                <div>
                    <span class="status-indicator status-connecting" id="ws-status"></span>
                    WebSocket: <span id="ws-status-text">Connecting...</span>
                </div>
                <div>
                    <span class="status-indicator status-connecting" id="api-status"></span>
                    API: <span id="api-status-text">Checking...</span>
                </div>
            </div>
            
            <div class="mt-4">
                <h4 class="font-bold mb-2">3° Separation</h4>
                <div>Status: <span id="separation-status">OFF</span></div>
                <div>Filter: <span id="separation-filter">0</span></div>
            </div>
        </div>
    </div>
    
    <!-- Dart WebAssembly Application -->
    <script src="village_map_simple.dart.js" defer></script>
    <script>
        // Global application state for Dart interop
        const VillageApp = {
            state: {
                isLoading: true,
                is3DEnabled: true,
                isSeparationEnabled: false,
                minReputation: 0,
                nodeDensity: 50,
                fps: 60,
                currentUser: null,
                permissions: {},
                nodeCount: 0,
                visibleCount: 0,
                wsConnected: false,
                dartInstance: null
            },
            
            // Initialize application
            async init() {
                console.log('Initializing The Village...');
                
                // Check authentication
                await this.checkAuth();
                
                // Initialize Dart WebAssembly
                this.renderer = this.createRenderer();
                
                // Load test data
                await this.loadTestData();
                
                // Setup event listeners
                this.setupEventListeners();
                
                // Start animation loop
                this.startAnimation();
                
                // Hide loading screen
                this.hideLoading();
                
                // Force initial render
                this.renderScene();
                
                console.log('The Village initialized successfully');
            // Setup event listeners
            this.setupEventListeners();
            
            // Start animation loop
            this.startAnimation();
            
            // Hide loading screen
            this.hideLoading();
            
            // Force initial render
            this.renderScene();
            
            console.log('The Village initialized successfully');
            },
            
            // Check user authentication
            async checkAuth() {
                try {
                    const response = await fetch('/api/user');
                    
                    if (response.ok) {
                        const data = await response.json();
                        this.state.currentUser = data.user;
                        this.state.permissions = data.permissions;
                        this.state.apiConnected = true;
                        this.updateStatus('api', true);
                        this.updateUserRoleDisplay();
                    } else {
                        // Load test users for demo
                        await this.loadTestUsers();
                    }
                } catch (error) {
                    console.error('Auth check failed:', error);
                    this.updateStatus('api', false);
                    // Load test users for demo
                    await this.loadTestUsers();
                }
            },

            // Load test users for demo
            async loadTestUsers() {
                try {
                    const response = await fetch('/api/auth/test-users');
                    const data = await response.json();
                    
                    // Auto-login as first user for demo
                    if (data.users && data.users.length > 0) {
                        const firstUser = data.users[0];
                        await this.loginAsUser(firstUser.id);
                    }
                } catch (error) {
                    console.error('Failed to load test users:', error);
                }
            },

            // Login as specific user
            async loginAsUser(userId) {
                try {
                    const response = await fetch('/api/auth/login-as', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            userId: userId
                        })
                    });
                    
                    if (response.ok) {
                        const data = await response.json();
                        this.state.currentUser = data.user;
                        this.state.permissions = data.permissions;
                        this.updateUserRoleDisplay();
                        console.log('Logged in as:', data.user.display_name);
                    } else {
                        console.warn('User not authenticated, using demo mode');
                        this.setDemoUser();
                    }
                } catch (error) {
                    console.warn('Auth check failed, using demo mode:', error);
                    this.setDemoUser();
                }
            },

            // Switch user role
            async switchRole(role) {
                try {
                    const response = await fetch('/api/auth/switch-role', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            role: role
                        })
                    });
                    
                    if (response.ok) {
                        const data = await response.json();
                        this.state.currentUser = data.user;
                        this.state.permissions = data.permissions;
                        this.updateUserRoleDisplay();
                        console.log('Switched to role:', role);
                        
                        // Update 3D view based on new role
                        this.updateViewForRole(role);
                    }
                } catch (error) {
                    console.error('Failed to switch role:', error);
                }
            },

            // Update user role display
            updateUserRoleDisplay() {
                if (this.state.currentUser) {
                    const user = this.state.currentUser;
                    document.getElementById('current-role').textContent = user.role.charAt(0).toUpperCase() + user.role.slice(1);
                    document.getElementById('current-user').textContent = user.display_name;
                    document.getElementById('current-reputation').textContent = user.base_integrity || 0;
                }
            },

            // Update view based on user role
            updateViewForRole(role) {
                // Adjust what's visible based on role permissions
                const permissions = this.state.permissions;
                
                // Hide/show features based on permissions
                if (!permissions.can_view_domiciles) {
                    console.log('Domiciles hidden for role:', role);
                }
                
                if (!permissions.can_create_domicile) {
                    console.log('Domicile creation disabled for role:', role);
                }
                
                // Update 3D scene based on role
                this.updateSceneForRole(role);
            },

            // Update 3D scene for role
            updateSceneForRole(role) {
                // Filter nodes based on role
                if (role === 'stranger') {
                    // Strangers see limited village information
                    this.state.visibleCount = Math.floor(this.state.nodeCount * 0.3);
                } else if (role === 'sojourner') {
                    // Sojourners see more but still limited
                    this.state.visibleCount = Math.floor(this.state.nodeCount * 0.6);
                } else {
                    // Denizens and above see everything
                    this.state.visibleCount = this.state.nodeCount;
                }
                
                this.updateStats();
            },
            
            // Initialize WebSocket connection
            async initWebSocket() {
                try {
                    // Connect to Reverb WebSocket
                    this.ws = new WebSocket('ws://localhost:8080/app/reverb_key');
                    
                    this.ws.onopen = () => {
                        console.log('WebSocket connected');
                        this.state.wsConnected = true;
                        this.updateStatus('ws', true);
                        
                        // Join presence channel
                        this.ws.send(JSON.stringify({
                            event: 'pusher:subscribe',
                            data: {
                                channel: 'presence-village'
                            }
                        }));
                    };
                    
                    this.ws.onmessage = (event) => {
                        const data = JSON.parse(event.data);
                        this.handleWebSocketMessage(data);
                    };
                    
                    this.ws.onclose = () => {
                        console.log('WebSocket disconnected');
                        this.state.wsConnected = false;
                        this.updateStatus('ws', false);
                        
                        // Attempt reconnection after 3 seconds
                        setTimeout(() => this.initWebSocket(), 3000);
                    };
                    
                    this.ws.onerror = (error) => {
                        console.error('WebSocket error:', error);
                        this.updateStatus('ws', false);
                    };
                } catch (error) {
                    console.error('WebSocket initialization failed:', error);
                    this.updateStatus('ws', false);
                }
            },
            
            // Initialize 3D scene
            async init3DScene() {
                const canvas = document.getElementById('village-canvas');
                
                // Initialize WebGL context
                this.gl = canvas.getContext('webgl2') || canvas.getContext('webgl');
                
                if (!this.gl) {
                    throw new Error('WebGL not supported');
                }
                
                // Set canvas size
                this.resizeCanvas();
                
                // Initialize scene graph
                this.sceneGraph = this.createSceneGraph();
                
                // Initialize renderer
                this.renderer = this.createRenderer();
                
                // Load test data
                await this.loadTestData();
            },
            
            // Create scene graph
            createSceneGraph() {
                return {
                    nodes: [],
                    edges: [],
                    camera: {
                        position: { x: 0, y: 10, z: 20 },
                        rotation: 0,
                        fov: 60
                    }
                };
            },
            
            // Create renderer
            createRenderer() {
                return {
                    gl: this.gl,
                    program: this.createShaderProgram(),
                    render: () => this.renderScene()
                };
            },
            
            // Create shader program
            createShaderProgram() {
                const vertexShaderSource = `
                    attribute vec3 aPosition;
                    uniform mat4 uProjectionMatrix;
                    uniform mat4 uViewMatrix;
                    uniform mat4 uModelMatrix;
                    
                    void main() {
                        gl_Position = uProjectionMatrix * uViewMatrix * uModelMatrix * vec4(aPosition, 1.0);
                    }
                `;
                
                const fragmentShaderSource = `
                    precision mediump float;
                    uniform vec4 uColor;
                    
                    void main() {
                        gl_FragColor = uColor;
                    }
                `;
                
                // Compile shaders (simplified)
                return { vertexShaderSource, fragmentShaderSource };
            },
            
            // Load test data
            async loadTestData() {
                try {
                    // Initialize sceneGraph first
                    if (!this.sceneGraph) {
                        this.createTestNodes();
                    }
                    
                    // Load test users
                    try {
                        const usersResponse = await fetch('/api/test-users');
                        if (usersResponse.ok) {
                            const usersData = await usersResponse.json();
                            this.state.users = usersData.users;
                        }
                    } catch (error) {
                        console.warn('Failed to load test users:', error);
                    }
                    
                    // Load villages
                    try {
                        const villagesResponse = await fetch('/api/villages');
                        if (villagesResponse.ok) {
                            const villagesData = await villagesResponse.json();
                            this.sceneGraph.nodes = villagesData.map(village => ({
                                id: village.id,
                                name: village.name,
                                position: { x: Math.random() * 200 - 100, y: Math.random() * 200 - 100, z: 0 },
                                color: { r: 0.2, g: 0.6, b: 1.0 },
                                size: 20,
                                visible: true
                            }));
                        }
                    } catch (error) {
                        console.warn('Failed to load villages:', error);
                    }
                    
                    this.state.nodeCount = this.sceneGraph.nodes.length;
                    this.updateStats();
                } catch (error) {
                    console.error('Failed to load test data:', error);
                    // Final fallback
                    this.createTestNodes();
                }
            },
            
            // Create test nodes
            createTestNodes() {
                // Initialize sceneGraph if undefined
                if (!this.sceneGraph) {
                    this.sceneGraph = {
                        nodes: [],
                        edges: [],
                        camera: {
                            position: { x: 0, y: 10, z: 20 },
                            rotation: 0
                        }
                    };
                }
                
                const testNodes = [
                    { name: 'Tech Village', color: { r: 0.2, g: 0.6, b: 1.0 } },
                    { name: 'Art Village', color: { r: 1.0, g: 0.2, b: 0.6 } },
                    { name: 'Science Village', color: { r: 0.6, g: 0.2, b: 1.0 } }
                ];
                
                testNodes.forEach((node, index) => {
                    const angle = (index / testNodes.length) * Math.PI * 2;
                    const radius = 200;
                    
                    this.sceneGraph.nodes.push({
                        id: `test-${index}`,
                        name: node.name,
                        position: {
                            x: Math.cos(angle) * radius,
                            y: 0,
                            z: Math.sin(angle) * radius
                        },
                        color: node.color,
                        size: 20,
                        type: 'village'
                    });
                });
                
                this.state.nodeCount = this.sceneGraph.nodes.length;
                this.updateStats();
            },
            
            // Setup event listeners
            setupEventListeners() {
                // Role switching
                document.getElementById('switch-role-btn').addEventListener('click', () => {
                    const roleSelect = document.getElementById('role-select');
                    const selectedRole = roleSelect.value;
                    
                    if (selectedRole) {
                        this.switchRole(selectedRole);
                    }
                });
                
                // Control buttons
                document.getElementById('toggle-3d').addEventListener('click', () => {
                    this.toggle3D();
                });
                
                document.getElementById('toggle-separation').addEventListener('click', () => {
                    this.toggleSeparation();
                });
                
                document.getElementById('reset-camera').addEventListener('click', () => {
                    this.resetCamera();
                });
                
                document.getElementById('toggle-fullscreen').addEventListener('click', () => {
                    this.toggleFullscreen();
                });
                
                // Sliders
                document.getElementById('reputation-slider').addEventListener('input', (e) => {
                    this.state.minReputation = parseInt(e.target.value);
                    document.getElementById('reputation-value').textContent = this.state.minReputation;
                    this.applyFilters();
                });
                
                document.getElementById('density-slider').addEventListener('input', (e) => {
                    this.state.nodeDensity = parseInt(e.target.value);
                    document.getElementById('density-value').textContent = this.state.nodeDensity;
                    this.updateNodeDensity();
                });
                
                // Window resize
                window.addEventListener('resize', () => this.resizeCanvas());
                
                // Keyboard shortcuts
                document.addEventListener('keydown', (e) => {
                    if (e.key === 'f') this.toggleFullscreen();
                    if (e.key === 'r') this.resetCamera();
                    if (e.key === '3') this.toggle3D();
                    if (e.key === 's') this.toggleSeparation();
                });
            },
            
            // Toggle 3D mode
            toggle3D() {
                this.state.is3DEnabled = !this.state.is3DEnabled;
                console.log('3D mode:', this.state.is3DEnabled ? 'ON' : 'OFF');
            },
            
            // Toggle 3-degree separation
            toggleSeparation() {
                this.state.isSeparationEnabled = !this.state.isSeparationEnabled;
                document.getElementById('separation-status').textContent = 
                    this.state.isSeparationEnabled ? 'ON' : 'OFF';
                this.applyFilters();
            },
            
            // Reset camera
            resetCamera() {
                this.sceneGraph.camera.position = { x: 0, y: 10, z: 20 };
                this.sceneGraph.camera.rotation = 0;
                console.log('Camera reset');
            },
            
            // Toggle fullscreen
            toggleFullscreen() {
                if (!document.fullscreenElement) {
                    document.documentElement.requestFullscreen();
                } else {
                    document.exitFullscreen();
                }
            },
            
            // Apply filters
            applyFilters() {
                if (this.state.isSeparationEnabled) {
                    // Filter nodes based on reputation
                    const filteredNodes = this.sceneGraph.nodes.filter(node => 
                        node.reputation >= this.state.minReputation
                    );
                    this.state.visibleCount = filteredNodes.length;
                } else {
                    this.state.visibleCount = this.state.nodeCount;
                }
                
                document.getElementById('separation-filter').textContent = 
                    this.state.visibleCount;
                this.updateStats();
            },
            
            // Update node density
            updateNodeDensity() {
                // Adjust visible nodes based on density
                const maxNodes = this.state.nodeDensity;
                const visibleNodes = this.sceneGraph.nodes.slice(0, maxNodes);
                this.state.visibleCount = visibleNodes.length;
                this.updateStats();
            },
            
            // Resize canvas
            resizeCanvas() {
                const canvas = document.getElementById('village-canvas');
                canvas.width = window.innerWidth;
                canvas.height = window.innerHeight;
                
                if (this.gl) {
                    this.gl.viewport(0, 0, canvas.width, canvas.height);
                }
            },
            
            // Render scene
            renderScene() {
                if (!this.gl || !this.state.is3DEnabled) return;
                
                // Clear canvas
                this.gl.clearColor(0.4, 0.5, 0.7, 1.0);
                this.gl.clear(this.gl.COLOR_BUFFER_BIT | this.gl.DEPTH_BUFFER_BIT);
                
                // Enable depth testing
                this.gl.enable(this.gl.DEPTH_TEST);
                
                // Render nodes
                this.sceneGraph.nodes.forEach(node => {
                    this.renderNode(node);
                });
            },
            
            // Create projection matrix
            createProjectionMatrix() {
                const gl = this.gl;
                const fov = Math.PI / 4; // 45 degrees
                const aspect = gl.canvas.width / gl.canvas.height;
                const near = 0.1;
                const far = 1000.0;
                
                const f = 1.0 / Math.tan(fov / 2);
                const rangeInv = 1 / (near - far);
                
                return new Float32Array([
                    f / aspect, 0, 0, 0,
                    0, f, 0, 0,
                    0, 0, rangeInv, -1,
                    0, 0, (near + far) * rangeInv, -2 * near * far * rangeInv
                ]);
            },
            
            // Create view matrix
            createViewMatrix() {
                const gl = this.gl;
                const camera = this.sceneGraph.camera;
                
                // Create view matrix (camera at origin looking down negative z)
                const eye = [camera.position.x, camera.position.y, camera.position.z];
                const center = [0, 0, 0];
                const up = [0, 1, 0];
                
                return this.lookAt(eye, center, up);
            },
            
            // Create model matrix
            createModelMatrix(position) {
                const gl = this.gl;
                
                // Translation
                const translation = [
                    1, 0, 0, 0,
                    0, 1, 0, 0,
                    0, 0, 1, 0,
                    0, 0, 0, 1,
                    position.x, position.y, position.z, 1
                ];
                
                return translation;
            },
            
            // Look at matrix helper
            lookAt(eye, center, up) {
                const zAxis = this.normalize([
                    up[2] * (center[1] - eye[1]) - up[1] * (center[2] - eye[2]),
                    up[0] * (center[2] - eye[2]) - up[2] * (center[0] - eye[0]),
                    up[1] * (center[0] - eye[0]) - up[0] * (center[1] - eye[1])
                ]);
                
                const xAxis = this.normalize([
                    (center[1] - eye[1]) * zAxis[2] - (center[2] - eye[2]) * zAxis[1],
                    (center[0] - eye[0]) * zAxis[2] - (center[2] - eye[2]) * zAxis[0],
                    (center[0] - eye[0]) * zAxis[1] - (center[1] - eye[1]) * zAxis[0]
                ]);
                
                const yAxis = [
                    center[0] - eye[0],
                    center[1] - eye[1],
                    center[2] - eye[2]
                ];
                
                return [
                    xAxis[0], yAxis[0], zAxis[0], 0,
                    xAxis[1], yAxis[1], zAxis[1], 0,
                    -xAxis[0], -yAxis[0], -zAxis[0], 0,
                    -(xAxis[0] * eye[0] + yAxis[0] * eye[1] + zAxis[0] * eye[2]),
                    -(xAxis[1] * eye[0] + yAxis[1] * eye[1] + zAxis[1] * eye[2]),
                    1
                ];
            },
            
            // Normalize vector
            normalize(v) {
                const length = Math.sqrt(v[0] * v[0] + v[1] * v[1] + v[2] * v[2]);
                return [v[0] / length, v[1] / length, v[2] / length];
            },

            // Render individual node
            renderNode(node) {
                const gl = this.gl;
                
                // Create simple geometry (square)
                const size = node.size || 20;
                const vertices = new Float32Array([
                    // Square vertices
                    node.position.x - size/2, node.position.y - size/2, 0.0,
                    node.position.x + size/2, node.position.y - size/2, 0.0,
                    node.position.x + size/2, node.position.y + size/2, 0.0,
                    node.position.x - size/2, node.position.y + size/2, 0.0,
                    node.position.x + size/2, node.position.y + size/2, 0.0
                ]);
                
                // Create buffer
                const buffer = gl.createBuffer();
                gl.bindBuffer(gl.ARRAY_BUFFER, buffer);
                gl.bufferData(gl.ARRAY_BUFFER, vertices, gl.STATIC_DRAW);
                
                // Simple colored square rendering
                const color = node.color || { r: 0.2, g: 0.6, b: 1.0 };
                
                // Create simple shader
                const vertexShaderSource = `
                    attribute vec3 aPosition;
                    uniform mat4 uProjectionMatrix;
                    uniform mat4 uViewMatrix;
                    uniform mat4 uModelMatrix;
                    uniform vec4 uColor;
                    
                    void main() {
                        gl_Position = uProjectionMatrix * uViewMatrix * uModelMatrix * vec4(aPosition, 1.0);
                    }
                `;
                
                const fragmentShaderSource = `
                    precision mediump float;
                    uniform vec4 uColor;
                    
                    void main() {
                        gl_FragColor = uColor;
                    }
                `;
                
                // Compile shaders
                const vertexShader = gl.createShader(gl.VERTEX_SHADER);
                gl.shaderSource(vertexShader, vertexShaderSource);
                gl.compileShader(vertexShader);
                
                const fragmentShader = gl.createShader(gl.FRAGMENT_SHADER);
                gl.shaderSource(fragmentShader, fragmentShaderSource);
                gl.compileShader(fragmentShader);
                
                // Create and use program
                const program = gl.createProgram();
                gl.attachShader(program, vertexShader);
                gl.attachShader(program, fragmentShader);
                gl.linkProgram(program);
                gl.useProgram(program);
                
                // Check for shader compilation errors
                if (!gl.getShaderParameter(vertexShader, gl.COMPILE_STATUS)) {
                    console.error('Vertex shader compilation error:', gl.getShaderInfoLog(vertexShader));
                    return;
                }
                
                if (!gl.getShaderParameter(fragmentShader, gl.COMPILE_STATUS)) {
                    console.error('Fragment shader compilation error:', gl.getShaderInfoLog(fragmentShader));
                    return;
                }
                
                if (!gl.getProgramParameter(program, gl.LINK_STATUS)) {
                    console.error('Shader program linking error:', gl.getProgramInfoLog(program));
                    return;
                }
                
                // Set uniforms
                const projectionMatrix = this.createProjectionMatrix();
                const viewMatrix = this.createViewMatrix();
                const modelMatrix = this.createModelMatrix(node.position);
                
                const projectionLocation = gl.getUniformLocation(program, 'uProjectionMatrix');
                const viewLocation = gl.getUniformLocation(program, 'uViewMatrix');
                const modelLocation = gl.getUniformLocation(program, 'uModelMatrix');
                const colorLocation = gl.getUniformLocation(program, 'uColor');
                
                gl.uniformMatrix4fv(projectionLocation, false, projectionMatrix);
                gl.uniformMatrix4fv(viewLocation, false, viewMatrix);
                gl.uniformMatrix4fv(modelLocation, false, modelMatrix);
                gl.uniform4f(colorLocation, color.r, color.g, color.b, 1.0);
                
                // Set attribute
                const positionLocation = gl.getAttribLocation(program, 'aPosition');
                gl.enableVertexAttribArray(positionLocation);
                gl.vertexAttribPointer(positionLocation, 3, gl.FLOAT, false, 0, 0);
                
                // Draw
                gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4);
                
                // Cleanup
                gl.deleteBuffer(buffer);
                gl.deleteProgram(program);
                gl.deleteShader(vertexShader);
                gl.deleteShader(fragmentShader);
            },
            
            // Handle WebSocket messages
            handleWebSocketMessage(data) {
                if (data.event === 'peer_discovery') {
                    console.log('Peer discovery event:', data);
                    // Update scene with new peer
                    this.addPeerNode(data.data);
                }
            },
            
            // Add peer node to scene
            addPeerNode(peerData) {
                this.sceneGraph.nodes.push({
                    id: peerData.id,
                    name: peerData.name,
                    position: peerData.position || { x: 0, y: 0, z: 0 },
                    color: { r: 0.8, g: 0.4, b: 0.8 },
                    size: 10,
                    type: 'user'
                });
                
                this.state.nodeCount++;
                this.updateStats();
            },
            
            // Update status indicators
            updateStatus(type, connected) {
                const statusElement = document.getElementById(`${type}-status`);
                const textElement = document.getElementById(`${type}-status-text`);
                
                if (connected) {
                    statusElement.className = 'status-indicator status-online';
                    textElement.textContent = 'Online';
                } else {
                    statusElement.className = 'status-indicator status-offline';
                    textElement.textContent = 'Offline';
                }
            },
            
            // Update statistics
            updateStats() {
                document.getElementById('fps').textContent = this.state.fps.toFixed(0);
                document.getElementById('node-count').textContent = this.state.nodeCount;
                document.getElementById('visible-count').textContent = this.state.visibleCount;
                document.getElementById('connection-count').textContent = this.state.connectionCount;
            },
            
            // Start animation loop
            startAnimation() {
                let lastTime = 0;
                let frameCount = 0;
                let fpsTime = 0;
                
                const animate = (currentTime) => {
                    const deltaTime = (currentTime - lastTime) / 1000.0;
                    lastTime = currentTime;
                    
                    // Update FPS counter
                    frameCount++;
                    fpsTime += deltaTime;
                    if (fpsTime >= 1.0) {
                        this.state.fps = frameCount;
                        frameCount = 0;
                        fpsTime = 0;
                        this.updateStats();
                    }
                    
                    // Update camera rotation
                    this.sceneGraph.camera.rotation += 0.005;
                    
                    // Render scene
                    this.renderer.render();
                    
                    // Continue animation
                    requestAnimationFrame(animate);
                };
                
                requestAnimationFrame(animate);
            },
            
            // Hide loading screen
            hideLoading() {
                document.getElementById('loading').style.display = 'none';
                document.getElementById('app').style.display = 'block';
                this.state.isLoading = false;
            },
            
            // Get auth token
            getAuthToken() {
                // Get token from localStorage or cookie
                return localStorage.getItem('auth_token') || 
                       document.cookie.split(';')
                       .find(row => row.trim().startsWith('auth_token='))
                       ?.split('=')[1];
            }
        };
        
        // Initialize application when DOM is ready
        document.addEventListener('DOMContentLoaded', () => {
            VillageApp.init();
        });
        
        // Handle errors
        window.addEventListener('error', (event) => {
            console.error('Application error:', event.error);
        });
        
        // Handle unhandled promises
        window.addEventListener('unhandledrejection', (event) => {
            console.error('Unhandled promise rejection:', event.reason);
        });
    </script>
</body>
</html>
