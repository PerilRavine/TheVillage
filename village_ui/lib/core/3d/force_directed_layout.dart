import 'dart:math';
import 'package:vector_math/vector_math.dart';
import 'scene_graph.dart';

/// Force-directed graph layout for village positioning
/// Implements Hooke's law for springs and Coulomb's law for repulsion
class ForceDirectedLayout {
  final SceneGraph _sceneGraph;
  final Map<String, Vector3> _nodePositions = {};
  final Map<String, Vector3> _nodeVelocities = {};
  final Map<String, double> _nodeMasses = {};
  
  // Physics parameters
  static const double _springConstant = 0.1;
  static const double _repulsionConstant = 1000.0;
  static const double _damping = 0.9;
  static const double _idealDistance = 200.0;
  static const double _minDistance = 50.0;
  
  ForceDirectedLayout(this._sceneGraph) {
    _initializeNodePositions();
    _initializeNodeMasses();
  }
  
  /// Update layout for one simulation step
  void update(double deltaTime) {
    _applyForces(deltaTime);
    _updatePositions(deltaTime);
    _constrainToBounds();
  }
  
  /// Apply spring and repulsion forces
  void _applyForces(double deltaTime) {
    final nodes = _sceneGraph.getAllNodes();
    
    for (final node in nodes) {
      final force = Vector3.zero();
      
      // Apply spring forces from connected nodes
      for (final edge in _sceneGraph.getAllEdges()) {
        if (edge.node1.id == node.id || edge.node2.id == node.id) {
          final otherNode = edge.node1.id == node.id ? edge.node2 : edge.node1;
          final springForce = _calculateSpringForce(node, otherNode, edge);
          force += springForce;
        }
      }
      
      // Apply repulsion forces from all other nodes
      for (final otherNode in nodes) {
        if (otherNode.id != node.id) {
          final repulsionForce = _calculateRepulsionForce(node, otherNode);
          force += repulsionForce;
        }
      }
      
      // Apply center gravity to keep graph centered
      final centerForce = _calculateCenterForce(node);
      force += centerForce;
      
      // Update velocity with force and damping
      final acceleration = force / _nodeMasses[node.id]!;
      _nodeVelocities[node.id] = (_nodeVelocities[node.id]! + acceleration * deltaTime) * _damping;
    }
  }
  
  /// Calculate spring force between two connected nodes
  Vector3 _calculateSpringForce(SceneNode node1, SceneNode node2, SceneEdge edge) {
    final displacement = node2.position - node1.position;
    final distance = displacement.length;
    
    if (distance < _minDistance) {
      return Vector3.zero();
    }
    
    // Hooke's law: F = -k * x
    final forceMagnitude = _springConstant * (distance - _idealDistance);
    final forceDirection = displacement.normalized();
    
    return forceDirection * forceMagnitude * _getEdgeWeight(edge);
  }
  
  /// Calculate repulsion force between two nodes
  Vector3 _calculateRepulsionForce(SceneNode node1, SceneNode node2) {
    final displacement = node2.position - node1.position;
    final distance = displacement.length;
    
    if (distance < _minDistance) {
      return Vector3.zero();
    }
    
    // Coulomb's law: F = k * q1 * q2 / r^2
    final forceMagnitude = _repulsionConstant / (distance * distance);
    final forceDirection = displacement.normalized();
    
    return -forceDirection * forceMagnitude; // Repel, not attract
  }
  
  /// Calculate center gravity force
  Vector3 _calculateCenterForce(SceneNode node) {
    final center = Vector3.zero();
    final displacement = center - node.position;
    final distance = displacement.length;
    
    if (distance < 1.0) {
      return Vector3.zero();
    }
    
    // Gentle pull toward center
    final forceMagnitude = 0.01;
    final forceDirection = displacement.normalized();
    
    return forceDirection * forceMagnitude;
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
