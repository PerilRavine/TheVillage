import 'dart:js_interop';
import 'dart:typed_data';
import 'package:vector_math/vector_math.dart';
import 'package:skwasm/skwasm.dart';

/// Native Dart 3D Engine for The Village Map
/// Uses Skwasm renderer for maximum WasmGC performance
class Village3DEngine {
  late final SkwasmCanvas _canvas;
  late final SkwasmRenderer _renderer;
  late final SceneGraph _sceneGraph;
  
  // Village positioning data for force-directed graph
  final Map<String, VillageNode> _villages = {};
  final Map<String, VillageEdge> _relationships = {};
  
  // Physics parameters for force-directed layout
  final double _springConstant = 0.1;
  final double _repulsionConstant = 1000.0;
  final double _damping = 0.9;
  final double _restLength = 100.0;
  
  Village3DEngine() {
    _initializeRenderer();
    _initializeSceneGraph();
  }
  
  void _initializeRenderer() {
    _canvas = SkwasmCanvas();
    _renderer = SkwasmRenderer(_canvas);
    
    // Configure for high-performance rendering
    _renderer.setAntialiasing(true);
    _renderer.setDepthBuffer(true);
    _renderer.setMaxEntities(1000);
  }
  
  void _initializeSceneGraph() {
    _sceneGraph = SceneGraph();
    _sceneGraph.enableSpatialPartitioning(true);
    _sceneGraph.enableLOD(true);
  }
  
  /// Add a village to the 3D scene
  void addVillage(String id, String name, Vector3 position, {
    double reputation = 0.0,
    int population = 0,
    String theme = 'default',
  }) {
    final villageNode = VillageNode(
      id: id,
      name: name,
      position: position,
      reputation: reputation,
      population: population,
      theme: theme,
    );
    
    _villages[id] = villageNode;
    _sceneGraph.addNode(villageNode);
  }
  
  /// Add relationship between villages for force-directed graph
  void addVillageRelationship(String village1Id, String village2Id, {
    double strength = 0.0,
    String relationshipType = 'neutral',
  }) {
    final edge = VillageEdge(
      village1Id: village1Id,
      village2Id: village2Id,
      strength: strength,
      relationshipType: relationshipType,
      restLength: _restLength * (1.0 - strength),
    );
    
    _relationships['${village1Id}-${village2Id}'] = edge;
    _sceneGraph.addEdge(edge);
  }
  
  /// Update force-directed graph positions using Hooke's and Coulomb's laws
  void updatePhysics(double deltaTime) {
    // Apply spring forces (Hooke's Law: F = -k * x)
    for (final edge in _relationships.values) {
      final node1 = _villages[edge.village1Id];
      final node2 = _villages[edge.village2Id];
      
      if (node1 == null || node2 == null) continue;
      
      final direction = (node2.position - node1.position).normalized();
      final distance = node1.position.distanceTo(node2.position);
      final displacement = distance - edge.restLength;
      final springForce = _springConstant * displacement;
      
      final force = direction * springForce;
      node1.applyForce(force);
      node2.applyForce(-force);
    }
    
    // Apply repulsion forces (Coulomb's Law: F = k * q1 * q2 / r²)
    final villageList = _villages.values.toList();
    for (int i = 0; i < villageList.length; i++) {
      for (int j = i + 1; j < villageList.length; j++) {
        final node1 = villageList[i];
        final node2 = villageList[j];
        
        final direction = (node2.position - node1.position).normalized();
        final distance = node1.position.distanceTo(node2.position);
        
        if (distance < 1.0) continue; // Avoid division by zero
        
        final repulsionForce = _repulsionConstant / (distance * distance);
        final force = direction * (-repulsionForce);
        
        node1.applyForce(force);
        node2.applyForce(-force);
      }
    }
    
    // Update positions with damping
    for (final village in _villages.values) {
      village.updatePosition(deltaTime, _damping);
    }
  }
  
  /// Render the 3D scene
  void render() {
    _renderer.clear();
    
    // Render village relationships first (behind villages)
    for (final edge in _relationships.values) {
      _renderEdge(edge);
    }
    
    // Render villages with LOD based on camera distance
    for (final village in _villages.values) {
      _renderVillage(village);
    }
    
    _renderer.present();
  }
  
  void _renderEdge(VillageEdge edge) {
    final node1 = _villages[edge.village1Id];
    final node2 = _villages[edge.village2Id];
    
    if (node1 == null || node2 == null) return;
    
    final color = _getRelationshipColor(edge.relationshipType, edge.strength);
    final thickness = 1.0 + edge.strength * 3.0;
    
    _renderer.drawLine(
      node1.position,
      node2.position,
      color,
      thickness,
    );
  }
  
  void _renderVillage(VillageNode village) {
    final lod = _calculateLOD(village.position);
    
    switch (lod) {
      case VillageLOD.high:
        _renderVillageHighDetail(village);
        break;
      case VillageLOD.medium:
        _renderVillageMediumDetail(village);
        break;
      case VillageLOD.low:
        _renderVillageLowDetail(village);
        break;
    }
  }
  
  void _renderVillageHighDetail(VillageNode village) {
    // Render full 3D model with reputation-based glow
    final glowIntensity = (village.reputation / 100.0).clamp(0.0, 1.0);
    final size = 10.0 + village.population / 10.0;
    
    _renderer.renderSphere(
      village.position,
      size,
      _getThemeColor(village.theme),
      glowIntensity,
    );
    
    // Render reputation aura
    if (village.reputation > 50.0) {
      _renderer.renderAura(
        village.position,
        size * 1.5,
        Colors.green.withOpacity(0.3),
      );
    }
  }
  
  void _renderVillageMediumDetail(VillageNode village) {
    // Render simplified sphere with basic color
    final size = 8.0 + village.population / 15.0;
    
    _renderer.renderSphere(
      village.position,
      size,
      _getThemeColor(village.theme),
      0.0,
    );
  }
  
  void _renderVillageLowDetail(VillageNode village) {
    // Render as simple point
    final size = 4.0;
    
    _renderer.renderPoint(
      village.position,
      size,
      _getThemeColor(village.theme),
    );
  }
  
  VillageLOD _calculateLOD(Vector3 position) {
    final distance = _sceneGraph.getCameraDistance(position);
    
    if (distance < 200.0) return VillageLOD.high;
    if (distance < 500.0) return VillageLOD.medium;
    return VillageLOD.low;
  }
  
  Color _getThemeColor(String theme) {
    switch (theme) {
      case 'forest':
        return Colors.green;
      case 'ocean':
        return Colors.blue;
      case 'mountain':
        return Colors.brown;
      case 'desert':
        return Colors.orange;
      default:
        return Colors.grey;
    }
  }
  
  Color _getRelationshipColor(String type, double strength) {
    final alpha = strength.clamp(0.2, 1.0);
    
    switch (type) {
      case 'allied':
        return Colors.green.withOpacity(alpha);
      case 'competitive':
        return Colors.yellow.withOpacity(alpha);
      case 'hostile':
        return Colors.red.withOpacity(alpha);
      default:
        return Colors.grey.withOpacity(alpha);
    }
  }
  
  /// Get villages within 3 degrees of separation
  List<VillageNode> getVillagesWithinThreeDegrees(String startVillageId) {
    final visited = <String>{};
    final queue = <String>[startVillageId];
    final result = <VillageNode>[];
    
    visited.add(startVillageId);
    
    for (int degree = 0; degree < 3 && queue.isNotEmpty; degree++) {
      final currentLevel = queue.length;
      
      for (int i = 0; i < currentLevel; i++) {
        final currentId = queue.removeAt(0);
        final village = _villages[currentId];
        
        if (village != null && degree > 0) {
          result.add(village);
        }
        
        // Find connected villages
        for (final edge in _relationships.values) {
          String? connectedId;
          
          if (edge.village1Id == currentId) {
            connectedId = edge.village2Id;
          } else if (edge.village2Id == currentId) {
            connectedId = edge.village1Id;
          }
          
          if (connectedId != null && !visited.contains(connectedId)) {
            visited.add(connectedId);
            queue.add(connectedId);
          }
        }
      }
    }
    
    return result;
  }
  
  /// Clean up resources
  void dispose() {
    _renderer.dispose();
    _canvas.dispose();
  }
}

class VillageNode {
  final String id;
  final String name;
  final String theme;
  final double reputation;
  final int population;
  
  Vector3 position;
  Vector3 velocity = Vector3.zero();
  Vector3 force = Vector3.zero();
  
  VillageNode({
    required this.id,
    required this.name,
    required this.position,
    required this.reputation,
    required this.population,
    required this.theme,
  });
  
  void applyForce(Vector3 newForce) {
    force += newForce;
  }
  
  void updatePosition(double deltaTime, double damping) {
    velocity += force * deltaTime;
    velocity *= damping;
    position += velocity * deltaTime;
    force = Vector3.zero();
  }
}

class VillageEdge {
  final String village1Id;
  final String village2Id;
  final double strength;
  final String relationshipType;
  final double restLength;
  
  VillageEdge({
    required this.village1Id,
    required this.village2Id,
    required this.strength,
    required this.relationshipType,
    required this.restLength,
  });
}

enum VillageLOD { high, medium, low }
