import 'dart:html';
import 'dart:math';

/// Simple Dart village map for research project
/// Demonstrates Dart Wasm integration for SRED grant
class VillageMapSimple {
  final CanvasElement _canvas;
  final CanvasRenderingContext2D _context;
  final List<VillageNode> _nodes = [];
  final List<Domicile> _domiciles = [];
  bool _isRunning = false;
  String _currentView = 'overview'; // 'overview' or 'village'
  VillageNode? _selectedVillage;
  
  VillageMapSimple() : 
    _canvas = querySelector('#village-canvas') as CanvasElement,
    _context = (querySelector('#village-canvas') as CanvasElement).context2D {
    
    _initializeVillages();
    _initializeDomiciles();
    _setupEventListeners();
    _startAnimation();
  }
  
  /// Initialize test villages
  void _initializeVillages() {
    _nodes.add(VillageNode('village1', 'Tech Village', 100, 0, 85.0));
    _nodes.add(VillageNode('village2', 'Art Village', -50, 87, 72.5));
    _nodes.add(VillageNode('village3', 'Science Village', -50, -87, 91.2));
  }
  
  /// Initialize test domiciles
  void _initializeDomiciles() {
    _domiciles.add(Domicile('domicile1', 'Demo User Home', 'village1', 50, 50));
    _domiciles.add(Domicile('domicile2', 'Artist Studio', 'village2', -80, 60));
    _domiciles.add(Domicile('domicile3', 'Science Lab', 'village3', -80, -60));
    _domiciles.add(Domicile('domicile4', 'Tech Hub', 'village1', 120, -30));
  }
  
  /// Setup event listeners for interaction
  void _setupEventListeners() {
    _canvas.onClick.listen((event) {
      final rect = _canvas.getBoundingClientRect();
      final x = event.client.x - rect.left;
      final y = event.client.y - rect.top;
      
      if (_currentView == 'overview') {
        _handleVillageClick(x.toDouble(), y.toDouble());
      } else if (_currentView == 'village') {
        _handleDomicileClick(x.toDouble(), y.toDouble());
      }
    });
  }
  
  /// Handle village click
  void _handleVillageClick(double x, double y) {
    final centerX = _canvas.width! / 2;
    final centerY = _canvas.height! / 2;
    
    for (final village in _nodes) {
      if (village.containsPoint(x, y, centerX, centerY)) {
        _enterVillage(village);
        break;
      }
    }
  }
  
  /// Handle domicile click
  void _handleDomicileClick(double x, double y) {
    final centerX = _canvas.width! / 2;
    final centerY = _canvas.height! / 2;
    
    // Check back button
    if (x >= 20 && x <= 120 && y >= 20 && y <= 60) {
      _exitVillage();
      return;
    }
    
    // Check domiciles
    for (final domicile in _domiciles) {
      if (domicile.villageId == _selectedVillage!.id && 
          domicile.containsPoint(x, y, centerX, centerY)) {
        _enterDomicile(domicile);
        break;
      }
    }
  }
  
  /// Enter village
  void _enterVillage(VillageNode village) {
    _selectedVillage = village;
    _currentView = 'village';
    print('Entering ${village.name}...');
  }
  
  /// Exit village
  void _exitVillage() {
    _selectedVillage = null;
    _currentView = 'overview';
    print('Returning to village overview...');
  }
  
  /// Enter domicile
  void _enterDomicile(Domicile domicile) {
    print('Entering domicile: ${domicile.name}');
    // TODO: Implement domicile interior view
  }
  
  /// Start animation loop
  void _startAnimation() {
    _isRunning = true;
    _animate();
  }
  
  /// Animation loop
  void _animate() {
    if (!_isRunning) return;
    
    _render();
    window.requestAnimationFrame((_) => _animate());
  }
  
  /// Render the village
  void _render() {
    // Clear canvas
    _context.clearRect(0, 0, _canvas.width!, _canvas.height!);
    
    // Draw background with gradient
    final gradient = _context.createLinearGradient(0, 0, 0, _canvas.height!);
    gradient.addColorStop(0, '#0f0f23');
    gradient.addColorStop(1, '#1a1a2e');
    _context.fillStyle = gradient;
    _context.fillRect(0, 0, _canvas.width!, _canvas.height!);
    
    if (_currentView == 'overview') {
      _renderOverview();
    } else if (_currentView == 'village') {
      _renderVillageInterior();
    }
  }
  
  /// Render overview view
  void _renderOverview() {
    // Draw title
    _context
      ..fillStyle = '#ffffff'
      ..font = 'bold 24px Arial'
      ..textAlign = 'center'
      ..fillText('The Village - P2P Trust Network', _canvas.width! / 2, 40);
    
    // Draw subtitle
    _context
      ..fillStyle = '#aaaaaa'
      ..font = '14px Arial'
      ..fillText('Click on any village to enter • Dart WasmGC Research Project', _canvas.width! / 2, 65);
    
    // Draw legend
    _drawLegend();
    
    // Draw villages
    for (final node in _nodes) {
      _drawVillage(node);
    }
    
    // Draw connections
    _drawConnections();
    
    // Draw instructions
    _drawOverviewInstructions();
  }
  
  /// Render village interior view
  void _renderVillageInterior() {
    if (_selectedVillage == null) return;
    
    // Draw title
    _context
      ..fillStyle = '#ffffff'
      ..font = 'bold 24px Arial'
      ..textAlign = 'center'
      ..fillText('${_selectedVillage!.name} - Interior View', _canvas.width! / 2, 40);
    
    // Draw back button
    _drawBackButton();
    
    // Draw village interior
    _drawVillageInterior();
    
    // Draw domiciles
    _drawDomiciles();
    
    // Draw interior instructions
    _drawInteriorInstructions();
  }
  
  /// Draw legend
  void _drawLegend() {
    final legendX = 20;
    final legendY = 100;
    
    _context
      ..fillStyle = 'rgba(0, 0, 0, 0.7)'
      ..fillRect(legendX - 10, legendY - 10, 200, 120)
      ..fillStyle = '#ffffff'
      ..font = 'bold 14px Arial'
      ..fillText('Legend', legendX, legendY + 10);
    
    _context
      ..fillStyle = '#00ff00'
      ..font = '12px Arial'
      ..fillText('● High Reputation (85%+)', legendX, legendY + 35);
    
    _context
      ..fillStyle = '#ffff00'
      ..fillText('● Medium Reputation (70-84%)', legendX, legendY + 55);
    
    _context
      ..fillStyle = '#ff6666'
      ..fillText('● Low Reputation (<70%)', legendX, legendY + 75);
    
    _context
      ..fillStyle = '#ffffff'
      ..fillText('Circle size = Reputation level', legendX, legendY + 95);
  }
  
  /// Draw overview instructions
  void _drawOverviewInstructions() {
    final instructions = [
      'Click on any village to enter and explore domiciles',
      'Each circle represents a village with its reputation score',
      'Lines show connections between villages',
      'Size indicates reputation level'
    ];
    
    final instructionY = _canvas.height! - 120;
    
    _context
      ..fillStyle = 'rgba(0, 0, 0, 0.7)'
      ..fillRect(20, instructionY - 10, _canvas.width! - 40, 110)
      ..fillStyle = '#ffffff'
      ..font = 'bold 14px Arial'
      ..fillText('How to explore:', 30, instructionY + 15);
    
    for (int i = 0; i < instructions.length; i++) {
      _context
        ..fillStyle = '#cccccc'
        ..font = '12px Arial'
        ..fillText('${i + 1}. ${instructions[i]}', 30, instructionY + 35 + (i * 20));
    }
  }
  
  /// Draw back button
  void _drawBackButton() {
    final buttonX = 20;
    final buttonY = 20;
    final buttonWidth = 100;
    final buttonHeight = 40;
    
    _context
      ..fillStyle = '#333333'
      ..fillRect(buttonX, buttonY, buttonWidth, buttonHeight)
      ..strokeStyle = '#ffffff'
      ..lineWidth = 2
      ..strokeRect(buttonX, buttonY, buttonWidth, buttonHeight)
      ..fillStyle = '#ffffff'
      ..font = 'bold 14px Arial'
      ..textAlign = 'center'
      ..fillText('← Back', buttonX + buttonWidth / 2, buttonY + 25);
  }
  
  /// Draw village interior
  void _drawVillageInterior() {
    final centerX = _canvas.width! / 2;
    final centerY = _canvas.height! / 2;
    
    // Draw village boundary
    _context
      ..strokeStyle = '#ffffff'
      ..lineWidth = 3
      ..beginPath()
      ..arc(centerX, centerY, 200, 0, 2 * pi)
      ..stroke();
    
    // Draw village name
    _context
      ..fillStyle = '#ffffff'
      ..font = 'bold 18px Arial'
      ..textAlign = 'center'
      ..fillText('${_selectedVillage!.name} Village', centerX, 100);
    
    // Draw reputation
    _context
      ..fillStyle = _selectedVillage!.reputation >= 85 ? '#00ff00' : 
                   _selectedVillage!.reputation >= 70 ? '#ffff00' : '#ff6666'
      ..font = 'bold 16px Arial'
      ..fillText('Reputation: ${_selectedVillage!.reputation.toStringAsFixed(1)}%', centerX, 130);
  }
  
  /// Draw domiciles
  void _drawDomiciles() {
    final centerX = _canvas.width! / 2;
    final centerY = _canvas.height! / 2;
    
    for (final domicile in _domiciles) {
      if (domicile.villageId != _selectedVillage!.id) continue;
      
      final x = centerX + domicile.x;
      final y = centerY + domicile.y;
      
      // Draw domicile as house shape
      _context
        ..fillStyle = '#8B4513' // Brown color for house
        ..fillRect(x - 20, y - 10, 40, 30) // House body
        ..fillStyle = '#FF6B6B' // Red roof
        ..beginPath()
        ..moveTo(x - 25, y - 10)
        ..lineTo(x, y - 30)
        ..lineTo(x + 25, y - 10)
        ..closePath()
        ..fill()
        ..fillStyle = '#ffffff'
        ..font = '10px Arial'
        ..textAlign = 'center'
        ..fillText(domicile.name, x, y + 30);
    }
  }
  
  /// Draw interior instructions
  void _drawInteriorInstructions() {
    final instructions = [
      'Click on any domicile to enter or manage it',
      'Each house represents a user\'s personal space',
      'Click "Back" to return to village overview',
      'Create your own domicile in this village'
    ];
    
    final instructionY = _canvas.height! - 120;
    
    _context
      ..fillStyle = 'rgba(0, 0, 0, 0.7)'
      ..fillRect(20, instructionY - 10, _canvas.width! - 40, 110)
      ..fillStyle = '#ffffff'
      ..font = 'bold 14px Arial'
      ..fillText('Inside the village:', 30, instructionY + 15);
    
    for (int i = 0; i < instructions.length; i++) {
      _context
        ..fillStyle = '#cccccc'
        ..font = '12px Arial'
        ..fillText('${i + 1}. ${instructions[i]}', 30, instructionY + 35 + (i * 20));
    }
  }
  
  /// Draw individual village
  void _drawVillage(VillageNode node) {
    final centerX = _canvas.width! / 2;
    final centerY = _canvas.height! / 2;
    
    // Calculate position
    final x = centerX + node.x;
    final y = centerY + node.y;
    
    // Draw village circle with size based on reputation
    final radius = 15 + (node.reputation / 100) * 25;
    _context
      ..fillStyle = node.color
      ..beginPath()
      ..arc(x, y, radius, 0, 2 * pi)
      ..fill()
      ..strokeStyle = '#ffffff'
      ..lineWidth = 3
      ..stroke();
    
    // Draw village name with background
    _context
      ..fillStyle = 'rgba(0, 0, 0, 0.7)'
      ..fillRect(x - 50, y - radius - 40, 100, 20)
      ..fillStyle = '#ffffff'
      ..font = 'bold 14px Arial'
      ..textAlign = 'center'
      ..fillText(node.name, x, y - radius - 25);
    
    // Draw reputation with color coding
    _context
      ..fillStyle = node.reputation >= 85 ? '#00ff00' : 
                   node.reputation >= 70 ? '#ffff00' : '#ff6666'
      ..font = 'bold 12px Arial'
      ..fillText('Reputation: ${node.reputation.toStringAsFixed(1)}%', x, y + radius + 20);
    
    // Draw click hint
    _context
      ..fillStyle = '#ffff00'
      ..font = '10px Arial'
      ..fillText('Click to enter', x, y - 5);
  }
  
  /// Draw connections between villages
  void _drawConnections() {
    final centerX = _canvas.width! / 2;
    final centerY = _canvas.height! / 2;
    
    _context.strokeStyle = 'rgba(255, 255, 255, 0.3)';
    _context.lineWidth = 1;
    
    for (int i = 0; i < _nodes.length; i++) {
      for (int j = i + 1; j < _nodes.length; j++) {
        final node1 = _nodes[i];
        final node2 = _nodes[j];
        
        _context.beginPath();
        _context.moveTo(centerX + node1.x, centerY + node1.y);
        _context.lineTo(centerX + node2.x, centerY + node2.y);
        _context.stroke();
      }
    }
  }
  
  /// Stop animation
  void stop() {
    _isRunning = false;
  }
}

/// Village node data structure
class VillageNode {
  final String id;
  final String name;
  final double x;
  final double y;
  final double reputation;
  
  VillageNode(this.id, this.name, this.x, this.y, this.reputation);
  
  /// Get color based on reputation
  String get color {
    if (reputation >= 90) return '#00ff00'; // Green - High reputation
    if (reputation >= 75) return '#ffff00'; // Yellow - Medium reputation
    return '#ff6666'; // Red - Low reputation
  }
  
  /// Check if point is within village bounds
  bool containsPoint(double px, double py, double centerX, double centerY) {
    final dx = px - (centerX + x);
    final dy = py - (centerY + y);
    final radius = 15 + (reputation / 100) * 25;
    return dx * dx + dy * dy <= radius * radius;
  }
}

/// Domicile data structure
class Domicile {
  final String id;
  final String name;
  final String villageId;
  final double x;
  final double y;
  
  Domicile(this.id, this.name, this.villageId, this.x, this.y);
  
  /// Check if point is within domicile bounds
  bool containsPoint(double px, double py, double centerX, double centerY) {
    final dx = px - (centerX + x);
    final dy = py - (centerY + y);
    return dx >= -25 && dx <= 25 && dy >= -30 && dy <= 20;
  }
}

/// Main entry point for Dart Wasm application
void main() {
  print('Initializing Dart Village Map for SRED Research Project...');
  
  // Create village map
  final villageMap = VillageMapSimple();
  
  print('Dart Village Map initialized successfully!');
  print('Research Goals:');
  print('- Dart WasmGC performance analysis');
  print('- 3D spatial computing innovation');
  print('- P2P trust network algorithms');
  print('- SRED grant preparation');
}
