import 'dart:collection';
import 'dart:js_interop';
import 'package:vector_math/vector_math.dart';

/// High-performance scene graph for 3D village rendering
/// Optimized for WasmGC with spatial partitioning and LOD
class SceneGraph {
  final List<SceneNode> _nodes = [];
  final List<SceneEdge> _edges = [];
  
  /// Visibility state for scene nodes
  bool visible = true;
  
  // Material properties
  String material = 'default';
  
  // Transform properties
  Vector3 position = Vector3.zero();
  Vector3 scale = Vector3.all(1.0);
  double rotation = 0.0;
  
  // Mass for physics simulation
  double mass = 1.0;
  
  // Spatial partitioning for performance
  final SpatialGrid _spatialGrid = SpatialGrid(cellSize: 100.0);
  
  // Camera for LOD calculations
  Vector3 _cameraPosition = Vector3.zero();
  double _cameraFOV = 60.0;
  
  bool _spatialPartitioningEnabled = false;
  bool _lodEnabled = false;
  
  SceneGraph();
  
  void enableSpatialPartitioning(bool enabled) {
    _spatialPartitioningEnabled = enabled;
    if (enabled) {
      _rebuildSpatialGrid();
    }
  }
  
  void enableLOD(bool enabled) {
    _lodEnabled = enabled;
  }
  
  void addNode(SceneNode node) {
    _nodes.add(node);
    if (_spatialPartitioningEnabled) {
      _spatialGrid.addNode(node);
    }
  }
  
  void addEdge(SceneEdge edge) {
    _edges.add(edge);
  }
  
  void removeNode(SceneNode node) {
    _nodes.remove(node);
    if (_spatialPartitioningEnabled) {
      _spatialGrid.removeNode(node);
    }
  }
  
  void removeEdge(SceneEdge edge) {
    _edges.remove(edge);
  }
  
  void updateCamera(Vector3 position, double fov) {
    _cameraPosition = position;
    _cameraFOV = fov;
    
    if (_spatialPartitioningEnabled) {
      _spatialGrid.updateCamera(position);
    }
  }
  
  /// Get nodes visible from camera with frustum culling
  List<SceneNode> getVisibleNodes() {
    if (!_spatialPartitioningEnabled) {
      return _nodes.where((node) => _isInFrustum(node)).toList();
    }
    
    return _spatialGrid.getVisibleNodes().where((node) => _isInFrustum(node)).toList();
  }
  
  /// Get nodes within a certain distance
  List<SceneNode> getNodesWithinDistance(Vector3 position, double distance) {
    if (!_spatialPartitioningEnabled) {
      return _nodes.where((node) => 
        node.position.distanceTo(position) <= distance
      ).toList();
    }
    
    return _spatialGrid.getNodesWithinRadius(position, distance);
  }
  
  /// Get camera distance for LOD calculations
  double getCameraDistance(Vector3 position) {
    return _cameraPosition.distanceTo(position);
  }
  
  bool _isInFrustum(SceneNode node) {
    final distance = _cameraPosition.distanceTo(node.position);
    final maxDistance = _calculateMaxVisibleDistance();
    
    return distance <= maxDistance;
  }
  
  double _calculateMaxVisibleDistance() {
    // Simple frustum calculation based on FOV
    return 1000.0; // Adjust based on your scene scale
  }
  
  void _rebuildSpatialGrid() {
    _spatialGrid.clear();
    for (final node in _nodes) {
      _spatialGrid.addNode(node);
    }
  }
  
  /// Update spatial partitioning when nodes move
  void updateNodePosition(SceneNode node) {
    if (_spatialPartitioningEnabled) {
      _spatialGrid.updateNode(node);
    }
  }
  
  List<SceneNode> getAllNodes() => List.unmodifiable(_nodes);
  List<SceneEdge> getAllEdges() => List.unmodifiable(_edges);
}

/// Base class for scene nodes
abstract class SceneNode {
  final String id;
  Vector3 position;
  final double boundingRadius;
  
  SceneNode({
    required this.id,
    required this.position,
    required this.boundingRadius,
  });
  
  void updatePosition(Vector3 newPosition) {
    position = newPosition;
  }
}

/// Connection between scene nodes
class SceneEdge {
  final String id;
  final SceneNode node1;
  final SceneNode node2;
  final Map<String, dynamic> metadata;
  
  SceneEdge({
    required this.id,
    required this.node1,
    required this.node2,
    this.metadata = const {},
  });
}

/// Spatial grid for efficient collision detection and culling
class SpatialGrid {
  final double cellSize;
  final Map<String, Set<SceneNode>> _grid = {};
  final Map<SceneNode, String> _nodeToCell = {};
  
  Vector3 _cameraPosition = Vector3.zero();
  static const int _visibleRadius = 5; // Grid cells to check around camera
  
  SpatialGrid({required this.cellSize});
  
  void addNode(SceneNode node) {
    final cellKey = _getCellKey(node.position);
    _grid.putIfAbsent(cellKey, () => <SceneNode>{});
    _grid[cellKey]!.add(node);
    _nodeToCell[node] = cellKey;
  }
  
  void removeNode(SceneNode node) {
    final cellKey = _nodeToCell[node];
    if (cellKey != null) {
      _grid[cellKey]?.remove(node);
      _nodeToCell.remove(node);
    }
  }
  
  void updateNode(SceneNode node) {
    removeNode(node);
    addNode(node);
  }
  
  void updateCamera(Vector3 position) {
    _cameraPosition = position;
  }
  
  List<SceneNode> getVisibleNodes() {
    final visibleNodes = <SceneNode>{};
    final cameraCell = _getCellKey(_cameraPosition);
    
    // Check cells within visible radius
    for (int x = -_visibleRadius; x <= _visibleRadius; x++) {
      for (int y = -_visibleRadius; y <= _visibleRadius; y++) {
        for (int z = -_visibleRadius; z <= _visibleRadius; z++) {
          final cellKey = _getCellKey(
            _cameraPosition + Vector3(x * cellSize, y * cellSize, z * cellSize)
          );
          
          final cellNodes = _grid[cellKey];
          if (cellNodes != null) {
            visibleNodes.addAll(cellNodes);
          }
        }
      }
    }
    
    return visibleNodes.toList();
  }
  
  List<SceneNode> getNodesWithinRadius(Vector3 center, double radius) {
    final nearbyNodes = <SceneNode>{};
    final radiusInCells = (radius / cellSize).ceil();
    
    for (int x = -radiusInCells; x <= radiusInCells; x++) {
      for (int y = -radiusInCells; y <= radiusInCells; y++) {
        for (int z = -radiusInCells; z <= radiusInCells; z++) {
          final cellKey = _getCellKey(
            center + Vector3(x * cellSize, y * cellSize, z * cellSize)
          );
          
          final cellNodes = _grid[cellKey];
          if (cellNodes != null) {
            for (final node in cellNodes) {
              if (node.position.distanceTo(center) <= radius) {
                nearbyNodes.add(node);
              }
            }
          }
        }
      }
    }
    
    return nearbyNodes.toList();
  }
  
  String _getCellKey(Vector3 position) {
    final x = (position.x / cellSize).floor();
    final y = (position.y / cellSize).floor();
    final z = (position.z / cellSize).floor();
    
    return '${x},${y},${z}';
  }
  
  void clear() {
    _grid.clear();
    _nodeToCell.clear();
  }
}
