import 'dart:html';
import 'dart:math';

/// 3D Village Designer for Admin Dashboard
/// Interactive tools for creating and customizing village layouts
class VillageDesigner {
  final CanvasElement _canvas;
  final CanvasRenderingContext2D _context;
  final List<Building> _buildings = [];
  final List<Road> _roads = [];
  final List<Zone> _zones = [];
  String _selectedTool = 'building';
  String _selectedBuildingType = 'residential';
  bool _isDragging = false;
  Point? _dragStart;
  Point? _dragEnd;
  
  VillageDesigner() : 
    _canvas = querySelector('#admin-canvas') as CanvasElement,
    _context = (querySelector('#admin-canvas') as CanvasElement).context2D {
    
    _setupEventListeners();
    _initializeDefaultLayout();
    _render();
  }
  
  /// Setup event listeners for interactive design
  void _setupEventListeners() {
    _canvas.onMouseDown.listen((event) {
      final rect = _canvas.getBoundingClientRect();
      final x = event.client.x - rect.left;
      final y = event.client.y - rect.top;
      
      _dragStart = Point(x.toDouble(), y.toDouble());
      _isDragging = true;
    });
    
    _canvas.onMouseMove.listen((event) {
      if (!_isDragging) return;
      
      final rect = _canvas.getBoundingClientRect();
      final x = event.client.x - rect.left;
      final y = event.client.y - rect.top;
      
      _dragEnd = Point(x.toDouble(), y.toDouble());
      _render();
    });
    
    _canvas.onMouseUp.listen((event) {
      if (!_isDragging) return;
      
      final rect = _canvas.getBoundingClientRect();
      final x = event.client.x - rect.left;
      final y = event.client.y - rect.top;
      
      _dragEnd = Point(x.toDouble(), y.toDouble());
      _handleToolAction();
      _isDragging = false;
    });
  }
  
  /// Initialize default village layout
  void _initializeDefaultLayout() {
    // Add default zones
    _zones.add(Zone('residential', 50, 50, 200, 150, '#4ECDC4'));
    _zones.add(Zone('commercial', 300, 50, 180, 120, '#FF6B6B'));
    _zones.add(Zone('public', 150, 250, 220, 100, '#9333EA'));
    
    // Add default roads
    _roads.add(Road(100, 0, 100, 400, 20));
    _roads.add(Road(0, 200, 400, 200, 20));
    
    // Add sample buildings
    _buildings.add(Building('house1', 'residential', 80, 80, 30, 40));
    _buildings.add(Building('shop1', 'commercial', 320, 80, 40, 35));
    _buildings.add(Building('townhall', 'public', 200, 280, 60, 50));
  }
  
  /// Handle tool actions based on selected tool
  void _handleToolAction() {
    if (_dragStart == null || _dragEnd == null) return;
    
    switch (_selectedTool) {
      case 'building':
        _addBuilding();
        break;
      case 'road':
        _addRoad();
        break;
      case 'zone':
        _addZone();
        break;
      case 'delete':
        _deleteElement();
        break;
    }
  }
  
  /// Add building to the design
  void _addBuilding() {
    final x = min(_dragStart!.x, _dragEnd!.x).toDouble();
    final y = min(_dragStart!.y, _dragEnd!.y).toDouble();
    final width = (_dragEnd!.x - _dragStart!.x).abs().toDouble();
    final height = (_dragEnd!.y - _dragStart!.y).abs().toDouble();
    
    if (width > 10 && height > 10) {
      final id = 'building_${_buildings.length + 1}';
      _buildings.add(Building(id, _selectedBuildingType, x, y, width, height));
      print('Added $_selectedBuildingType building at ($x, $y)');
    }
  }
  
  /// Add road to the design
  void _addRoad() {
    if (_dragStart != null && _dragEnd != null) {
      final road = Road(
        _dragStart!.x.toDouble(), _dragStart!.y.toDouble(),
        _dragEnd!.x.toDouble(), _dragEnd!.y.toDouble(),
        20.0
      );
      _roads.add(road);
      print('Added road from (${_dragStart!.x}, ${_dragStart!.y}) to (${_dragEnd!.x}, ${_dragEnd!.y})');
    }
  }
  
  /// Add zone to the design
  void _addZone() {
    final x = min(_dragStart!.x, _dragEnd!.x).toDouble();
    final y = min(_dragStart!.y, _dragEnd!.y).toDouble();
    final width = (_dragEnd!.x - _dragStart!.x).abs().toDouble();
    final height = (_dragEnd!.y - _dragStart!.y).abs().toDouble();
    
    if (width > 20 && height > 20) {
      final id = 'zone_${_zones.length + 1}';
      final color = _getZoneColor(_selectedBuildingType);
      _zones.add(Zone(id, x, y, width, height, color));
      print('Added $_selectedBuildingType zone at ($x, $y)');
    }
  }
  
  /// Delete element at click position
  void _deleteElement() {
    if (_dragStart == null) return;
    
    // Check buildings
    _buildings.removeWhere((building) => 
        building.contains(_dragStart!.x.toDouble(), _dragStart!.y.toDouble()));
    
    // Check roads
    _roads.removeWhere((road) => 
        road.isNear(_dragStart!.x.toDouble(), _dragStart!.y.toDouble()));
    
    // Check zones
    _zones.removeWhere((zone) => 
        zone.contains(_dragStart!.x.toDouble(), _dragStart!.y.toDouble()));
  }
  
  /// Get zone color based on type
  String _getZoneColor(String type) {
    switch (type) {
      case 'residential': return '#4ECDC4';
      case 'commercial': return '#FF6B6B';
      case 'public': return '#9333EA';
      case 'industrial': return '#FFA500';
      default: return '#666666';
    }
  }
  
  /// Render the village design
  void _render() {
    // Clear canvas
    _context.clearRect(0, 0, _canvas.width!, _canvas.height!);
    
    // Draw grid
    _drawGrid();
    
    // Draw zones
    for (final zone in _zones) {
      _drawZone(zone);
    }
    
    // Draw roads
    for (final road in _roads) {
      _drawRoad(road);
    }
    
    // Draw buildings
    for (final building in _buildings) {
      _drawBuilding(building);
    }
    
    // Draw current tool preview
    if (_isDragging && _dragStart != null && _dragEnd != null) {
      _drawToolPreview();
    }
    
    // Draw UI overlay
    _drawUIOverlay();
  }
  
  /// Draw background grid
  void _drawGrid() {
    _context.strokeStyle = 'rgba(255, 255, 255, 0.1)';
    _context.lineWidth = 1;
    
    final gridSize = 20;
    for (double x = 0; x <= _canvas.width!; x += gridSize) {
      _context.beginPath();
      _context.moveTo(x, 0);
      _context.lineTo(x, _canvas.height!);
      _context.stroke();
    }
    
    for (double y = 0; y <= _canvas.height!; y += gridSize) {
      _context.beginPath();
      _context.moveTo(0, y);
      _context.lineTo(_canvas.width!, y);
      _context.stroke();
    }
  }
  
  /// Draw zone
  void _drawZone(Zone zone) {
    _context.fillStyle = zone.color;
    _context.globalAlpha = 0.3;
    _context.fillRect(zone.x, zone.y, zone.width, zone.height);
    _context.globalAlpha = 1.0;
    
    _context.strokeStyle = zone.color;
    _context.lineWidth = 2;
    _context.strokeRect(zone.x, zone.y, zone.width, zone.height);
    
    // Draw zone label
    _context.fillStyle = '#ffffff';
    _context.font = '12px Arial';
    _context.textAlign = 'center';
    _context.fillText(zone.id, zone.x + zone.width / 2, zone.y + zone.height / 2);
  }
  
  /// Draw road
  void _drawRoad(Road road) {
    _context.strokeStyle = '#666666';
    _context.lineWidth = road.width;
    _context.lineCap = 'round';
    
    _context.beginPath();
    _context.moveTo(road.x1, road.y1);
    _context.lineTo(road.x2, road.y2);
    _context.stroke();
    
    // Draw road markings
    _context.strokeStyle = '#ffffff';
    _context.lineWidth = 2;
    _context.setLineDash([10, 10]);
    
    _context.beginPath();
    _context.moveTo(road.x1, road.y1);
    _context.lineTo(road.x2, road.y2);
    _context.stroke();
    
    _context.setLineDash([]);
  }
  
  /// Draw building
  void _drawBuilding(Building building) {
    // Draw building base
    _context.fillStyle = building.getColor();
    _context.fillRect(building.x, building.y, building.width, building.height);
    
    // Draw building outline
    _context.strokeStyle = '#ffffff';
    _context.lineWidth = 2;
    _context.strokeRect(building.x, building.y, building.width, building.height);
    
    // Draw roof
    _context.fillStyle = '#FF6B6B';
    _context.beginPath();
    _context.moveTo(building.x - 5, building.y);
    _context.lineTo(building.x + building.width / 2, building.y - 15);
    _context.lineTo(building.x + building.width + 5, building.y);
    _context.closePath();
    _context.fill();
    
    // Draw building label
    _context.fillStyle = '#ffffff';
    _context.font = '10px Arial';
    _context.textAlign = 'center';
    _context.fillText(building.type, building.x + building.width / 2, building.y + building.height / 2);
  }
  
  /// Draw tool preview
  void _drawToolPreview() {
    _context.strokeStyle = '#00ff88';
    _context.lineWidth = 2;
    _context.setLineDash([5, 5]);
    
    switch (_selectedTool) {
      case 'building':
      case 'zone':
        final x = min(_dragStart!.x, _dragEnd!.x);
        final y = min(_dragStart!.y, _dragEnd!.y);
        final width = (_dragEnd!.x - _dragStart!.x).abs();
        final height = (_dragEnd!.y - _dragStart!.y).abs();
        _context.strokeRect(x, y, width, height);
        break;
      case 'road':
        _context.beginPath();
        _context.moveTo(_dragStart!.x, _dragStart!.y);
        _context.lineTo(_dragEnd!.x, _dragEnd!.y);
        _context.stroke();
        break;
    }
    
    _context.setLineDash([]);
  }
  
  /// Draw UI overlay
  void _drawUIOverlay() {
    // Draw tool selection
    _context.fillStyle = 'rgba(0, 0, 0, 0.7)';
    _context.fillRect(10, 10, 150, 120);
    
    _context.fillStyle = '#ffffff';
    _context.font = 'bold 12px Arial';
    _context.textAlign = 'left';
    _context.fillText('Tools:', 20, 30);
    
    final tools = ['Building', 'Road', 'Zone', 'Delete'];
    for (int i = 0; i < tools.length; i++) {
      final y = 50 + (i * 20);
      _context.fillStyle = _selectedTool == tools[i].toLowerCase() ? '#00ff88' : '#ffffff';
      _context.fillText(tools[i], 20, y);
    }
  }
  
  /// Set selected tool
  void setTool(String tool) {
    _selectedTool = tool;
    _render();
  }
  
  /// Set selected building type
  void setBuildingType(String type) {
    _selectedBuildingType = type;
    _render();
  }
  
  /// Export village design as JSON
  Map<String, dynamic> exportDesign() {
    return {
      'buildings': _buildings.map((b) => b.toJson()).toList(),
      'roads': _roads.map((r) => r.toJson()).toList(),
      'zones': _zones.map((z) => z.toJson()).toList(),
      'metadata': {
        'created_at': DateTime.now().toIso8601String(),
        'version': '1.0.0'
      }
    };
  }
}

/// Building data structure
class Building {
  final String id;
  final String type;
  final double x;
  final double y;
  final double width;
  final double height;
  
  Building(this.id, this.type, this.x, this.y, this.width, this.height);
  
  String getColor() {
    switch (type) {
      case 'residential': return '#4ECDC4';
      case 'commercial': return '#FF6B6B';
      case 'public': return '#9333EA';
      case 'industrial': return '#FFA500';
      default: return '#666666';
    }
  }
  
  bool contains(double px, double py) {
    return px >= x && px <= x + width && py >= y && py <= y + height;
  }
  
  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'type': type,
      'x': x,
      'y': y,
      'width': width,
      'height': height
    };
  }
}

/// Road data structure
class Road {
  final double x1;
  final double y1;
  final double x2;
  final double y2;
  final double width;
  
  Road(this.x1, this.y1, this.x2, this.y2, this.width);
  
  bool isNear(double px, double py) {
    // Simple distance calculation from point to line
    final dist = _pointToLineDistance(px, py, x1, y1, x2, y2);
    return dist <= width;
  }
  
  double _pointToLineDistance(double px, double py, double x1, double y1, double x2, double y2) {
    final A = px - x1;
    final B = py - y1;
    final C = x2 - x1;
    final D = y2 - y1;
    
    final dot = A * C + B * D;
    final lenSq = C * C + D * D;
    double param = -1;
    
    if (lenSq != 0) param = dot / lenSq;
    
    double xx, yy;
    
    if (param < 0) {
      xx = x1;
      yy = y1;
    } else if (param > 1) {
      xx = x2;
      yy = y2;
    } else {
      xx = x1 + param * C;
      yy = y1 + param * D;
    }
    
    final dx = px - xx;
    final dy = py - yy;
    return sqrt(dx * dx + dy * dy);
  }
  
  Map<String, dynamic> toJson() {
    return {
      'x1': x1,
      'y1': y1,
      'x2': x2,
      'y2': y2,
      'width': width
    };
  }
}

/// Zone data structure
class Zone {
  final String id;
  final double x;
  final double y;
  final double width;
  final double height;
  final String color;
  
  Zone(this.id, this.x, this.y, this.width, this.height, this.color);
  
  bool contains(double px, double py) {
    return px >= x && px <= x + width && py >= y && py <= y + height;
  }
  
  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'x': x,
      'y': y,
      'width': width,
      'height': height,
      'color': color
    };
  }
}

/// Main entry point for village designer
void main() {
  print('Initializing Village Designer...');
  final designer = VillageDesigner();
  print('Village Designer initialized successfully!');
}
