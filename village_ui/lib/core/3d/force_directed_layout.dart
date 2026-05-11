import 'dart:math';
import 'package:vector_math/vector_math.dart';
import 'scene_graph.dart';

/// Force-directed graph layout algorithm for village positioning
/// Uses Hooke's law for spring forces and Coulomb's law for repulsion
class ForceDirectedLayout {
  final SceneGraph _sceneGraph;
  final Map<String, Vector3> _nodePositions = {};
  final Map<String, Vector3> _nodeVelocities = {};
  final double _springConstant = 0.1;
  final double _repulsionConstant = 1000.0;
  final double _damping = 0.95;
  final double _centerPull = 0.01;
  
  Vector3 _force = Vector3.zero();
  
  ForceDirectedLayout(this._sceneGraph) {
    _initializeNodePositions();
    _initializeNodeMasses();
  }
  
  /// Update layout for one simulation step
  void update(double deltaTime) {
    _applyForces(deltaTime);
    _updatePositions(deltaTime);
  }
  
  /// Apply spring and repulsion forces
  void _applyForces(double deltaTime) {
    for (final node in _sceneGraph.nodes) {
      _force = Vector3.zero();
      
      // Spring forces to connected nodes
      for (final edge in _sceneGraph.edges) {
        if (edge.source == node.id || edge.target == node.id) {
          final otherNode = _sceneGraph.getNodeById(
              edge.source == node.id ? edge.target : edge.source);
          if (otherNode != null) {
            final displacement = _nodePositions[otherNode!.id]! - _nodePositions[node.id]!;
            final distance = displacement.length;
            if (distance > 0.01) {
              final springForce = displacement.normalized() * _springConstant * (distance - 100.0);
              _force += springForce;
            }
          }
        }
      }
      
      // Repulsion forces from all other nodes
      for (final otherNode in _sceneGraph.nodes) {
        if (otherNode.id != node.id) {
          final displacement = _nodePositions[node.id]! - _nodePositions[otherNode.id]!;
          final distance = displacement.length;
          if (distance > 0.01 && distance < 200.0) {
            final repulsionForce = displacement.normalized() * 
                (_repulsionConstant / (distance * distance));
            _force += repulsionForce;
          }
        }
      }
      
      // Center pull force
      final centerForce = -_nodePositions[node.id]! * _centerPull;
      _force += centerForce;
      
      // Apply damping
      final velocity = _nodeVelocities[node.id]!;
      final dampedForce = _force - velocity * _damping;
      
      // Update velocity
      _nodeVelocities[node.id] = velocity + dampedForce * deltaTime;
    }
  }
  
  /// Update node positions based on velocities
  void _updatePositions(double deltaTime) {
    for (final entry in _nodePositions.entries) {
      final nodeId = entry.key;
      final position = entry.value;
      final velocity = _nodeVelocities[nodeId]!;
      
      // Update position
      final newPosition = position + velocity * deltaTime;
      _nodePositions[nodeId] = newPosition;
      
      // Update scene node position
      final node = _sceneGraph.getAllNodes().firstWhere((n) => n.id == nodeId);
      node.updatePosition(newPosition);
    }
  }
  
  /// Constrain positions to bounds
  void _constrainToBounds() {
    const bounds = 500.0;
    
    for (final entry in _nodePositions.entries) {
      final position = entry.value;
      
      // Clamp to bounds
      final clampedPosition = Vector3(
        position.x.clamp(-bounds, bounds),
        position.y.clamp(-bounds, bounds),
        position.z.clamp(-bounds, bounds),
      );
      
      _nodePositions[entry.key] = clampedPosition;
      
      // Stop velocity at bounds
      if (position.x.abs() >= bounds) _nodeVelocities[entry.key]!.x = 0;
      if (position.y.abs() >= bounds) _nodeVelocities[entry.key]!.y = 0;
      if (position.z.abs() >= bounds) _nodeVelocities[entry.key]!.z = 0;
    }
  }
  
  /// Initialize node positions in a circle
  void _initializeNodePositions() {
    final nodes = _sceneGraph.getAllNodes();
    final nodeCount = nodes.length;
    
    for (int i = 0; i < nodeCount; i++) {
      final angle = (2 * pi * i) / nodeCount;
      final radius = 200.0;
      
      final position = Vector3(
        cos(angle) * radius,
        0,
        sin(angle) * radius,
      );
      
      _nodePositions[nodes[i].id] = position;
      _nodeVelocities[nodes[i].id] = Vector3.zero();
    }
  }
  
  /// Initialize node masses based on reputation
  void _initializeNodeMasses() {
    final nodes = _sceneGraph.getAllNodes();
    
    for (final node in nodes) {
      // Mass based on reputation (higher reputation = more mass = less movement)
      final reputation = _getNodeReputation(node);
      final mass = 1.0 + (reputation / 100.0) * 2.0;
      _nodeMasses[node.id] = mass;
    }
  }
  
  /// Get reputation value for a node
  double _getNodeReputation(SceneNode node) {
    // This would be populated from the reputation system
    return 50.0; // Default value
  }
  
  /// Get edge weight for force calculation
  double _getEdgeWeight(SceneEdge edge) {
    // Stronger relationships have higher spring constants
    final metadata = edge.metadata;
    return metadata['strength'] ?? 1.0;
  }
  
  /// Check if layout has stabilized
  bool isStabilized() {
    double totalVelocity = 0.0;
    
    for (final velocity in _nodeVelocities.values) {
      totalVelocity += velocity.length;
    }
    
    final averageVelocity = totalVelocity / _nodeVelocities.length;
    return averageVelocity < 0.1; // Threshold for stabilization
  }
  
  /// Get current positions
  Map<String, Vector3> getPositions() {
    return Map.unmodifiable(_nodePositions);
  }
  
  /// Get current velocities
  Map<String, Vector3> getVelocities() {
    return Map.unmodifiable(_nodeVelocities);
  }
  
  /// Apply 3-degree separation filtering
  void applyThreeDegreeSeparation(String userId, int maxDepth) {
    final visitedNodes = <String>{};
    final nodesToFilter = <String>{};
    
    _bfsFromNode(userId, visitedNodes, nodesToFilter, maxDepth);
    
    // Hide filtered nodes
    for (final nodeId in nodesToFilter) {
      final node = _sceneGraph.getAllNodes().firstWhere(
        (n) => n.id == nodeId,
        orElse: () => throw Exception('Node not found: $nodeId'),
      );
      node.visible = false;
    }
  }
  
  /// Breadth-first search for 3-degree separation
  void _bfsFromNode(
    String startNodeId,
    Set<String> visitedNodes,
    Set<String> nodesToFilter,
    int maxDepth,
  ) {
    final queue = <QueueEntry>[QueueEntry(startNodeId, 0)];
    visitedNodes.add(startNodeId);
    
    while (queue.isNotEmpty) {
      final entry = queue.removeAt(0);
      
      if (entry.depth >= maxDepth) {
        continue;
      }
      
      // Get connected nodes
      final connectedEdges = _sceneGraph.getAllEdges().where((edge) =>
        edge.node1.id == entry.nodeId || edge.node2.id == entry.nodeId
      ).toList();
      
      for (final edge in connectedEdges) {
        final connectedNodeId = edge.node1.id == entry.nodeId 
          ? edge.node2.id 
          : edge.node1.id;
        
        if (!visitedNodes.contains(connectedNodeId)) {
          visitedNodes.add(connectedNodeId);
          queue.add(QueueEntry(connectedNodeId, entry.depth + 1));
        }
      }
    }
    
    // Add nodes beyond max depth to filter list
    for (final entry in queue) {
      if (entry.depth >= maxDepth) {
        nodesToFilter.add(entry.nodeId);
      }
    }
  }
}

/// Queue entry for BFS
class QueueEntry {
  final String nodeId;
  final int depth;
  
  const QueueEntry(this.nodeId, this.depth);
}
