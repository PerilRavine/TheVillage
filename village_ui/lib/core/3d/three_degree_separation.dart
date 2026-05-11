import 'dart:collection';
import 'scene_graph.dart';
import 'force_directed_layout.dart';

/// 3-degree separation logic for village filtering
/// Implements reputation-based access control and trust graph traversal
class ThreeDegreeSeparation {
  final SceneGraph _sceneGraph;
  final Map<String, Set<String>> _trustGraph = {};
  final Map<String, double> _reputationScores = {};
  
  ThreeDegreeSeparation(this._sceneGraph) {
    _buildTrustGraph();
    _initializeReputationScores();
  }
  
  /// Get accessible villages for a user within 3 degrees
  List<String> getAccessibleVillages(String userId, double minReputation) {
    final accessibleNodes = <String>{};
    final visitedNodes = <String>{};
    
    _bfsWithReputationFilter(
      userId, 
      visitedNodes, 
      accessibleNodes, 
      3, // 3 degrees of separation
      minReputation
    );
    
    return accessibleNodes.toList();
  }
  
  /// Check if user can access a specific village
  bool canAccessVillage(String userId, String villageId, double minReputation) {
    final accessibleVillages = getAccessibleVillages(userId, minReputation);
    return accessibleVillages.contains(villageId);
  }
  
  /// Get users within 3 degrees with reputation filtering
  List<String> getUsersWithinThreeDegrees(String userId, double minReputation) {
    final accessibleUsers = <String>{};
    final visitedNodes = <String>{};
    
    _bfsWithReputationFilter(
      userId, 
      visitedNodes, 
      accessibleUsers, 
      3, // 3 degrees of separation
      minReputation
    );
    
    return accessibleUsers.toList();
  }
  
  /// Calculate trust level between two users
  double calculateTrustLevel(String userId1, String userId2) {
    // Direct trust relationship
    if (_trustGraph.containsKey(userId1) && _trustGraph[userId1]!.contains(userId2)) {
      return 1.0; // Direct trust
    }
    
    // Calculate trust through reputation similarity
    final rep1 = _reputationScores[userId1] ?? 0.0;
    final rep2 = _reputationScores[userId2] ?? 0.0;
    final reputationSimilarity = 1.0 - (rep1 - rep2).abs() / 100.0;
    
    // Check for mutual connections
    final mutualConnections = _getMutualConnections(userId1, userId2);
    final trustBonus = mutualConnections.length * 0.1;
    
    return (reputationSimilarity + trustBonus).clamp(0.0, 1.0);
  }
  
  /// Build trust graph from scene edges
  void _buildTrustGraph() {
    final nodes = _sceneGraph.getAllNodes();
    final edges = _sceneGraph.getAllEdges();
    
    // Initialize trust graph
    for (final node in nodes) {
      _trustGraph[node.id] = <String>{};
    }
    
    // Build trust relationships from edges
    for (final edge in edges) {
      final strength = _getEdgeTrustStrength(edge);
      
      if (strength > 0.5) {
        // Add bidirectional trust for strong relationships
        _trustGraph[edge.node1.id]!.add(edge.node2.id);
        _trustGraph[edge.node2.id]!.add(edge.node1.id);
      }
    }
  }
  
  /// Initialize reputation scores (would come from backend)
  void _initializeReputationScores() {
    final nodes = _sceneGraph.getAllNodes();
    
    for (final node in nodes) {
      // This would be populated from backend API
      _reputationScores[node.id] = _getNodeReputation(node);
    }
  }
  
  /// BFS with reputation filtering
  void _bfsWithReputationFilter(
    String startUserId,
    Set<String> visitedNodes,
    Set<String> accessibleNodes,
    int maxDepth,
    double minReputation,
  ) {
    final queue = <QueueEntry>[QueueEntry(startUserId, 0)];
    visitedNodes.add(startUserId);
    
    while (queue.isNotEmpty) {
      final entry = queue.removeAt(0);
      
      if (entry.depth >= maxDepth) {
        continue;
      }
      
      // Check if current node meets reputation requirement
      final currentNodeReputation = _reputationScores[entry.nodeId] ?? 0.0;
      if (currentNodeReputation >= minReputation) {
        accessibleNodes.add(entry.nodeId);
      }
      
      // Get trusted connections
      final trustedConnections = _trustGraph[entry.nodeId] ?? <String>{};
      
      for (final connectedUserId in trustedConnections) {
        if (!visitedNodes.contains(connectedUserId)) {
          visitedNodes.add(connectedUserId);
          queue.add(QueueEntry(connectedUserId, entry.depth + 1));
        }
      }
    }
  }
  
  /// Get trust strength from edge metadata
  double _getEdgeTrustStrength(SceneEdge edge) {
    final metadata = edge.metadata;
    return metadata['trust_strength'] ?? 0.5;
  }
  
  /// Get mutual connections between two users
  Set<String> _getMutualConnections(String userId1, String userId2) {
    final connections1 = _trustGraph[userId1] ?? <String>{};
    final connections2 = _trustGraph[userId2] ?? <String>{};
    
    return connections1.intersection(connections2);
  }
  
  /// Get reputation for a node (placeholder)
  double _getNodeReputation(SceneNode node) {
    // This would come from backend reputation system
    // For now, use a simple hash-based calculation
    return node.id.hashCode.abs() % 100.0;
  }
  
  /// Update reputation scores from backend
  void updateReputationScores(Map<String, double> scores) {
    _reputationScores.addAll(scores);
  }
  
  /// Add new trust relationship
  void addTrustRelationship(String userId1, String userId2, double strength) {
    if (!_trustGraph.containsKey(userId1)) {
      _trustGraph[userId1] = <String>{};
    }
    if (!_trustGraph.containsKey(userId2)) {
      _trustGraph[userId2] = <String>{};
    }
    
    if (strength > 0.5) {
      _trustGraph[userId1]!.add(userId2);
      _trustGraph[userId2]!.add(userId1);
    }
  }
  
  /// Remove trust relationship
  void removeTrustRelationship(String userId1, String userId2) {
    _trustGraph[userId1]?.remove(userId2);
    _trustGraph[userId2]?.remove(userId1);
  }
  
  /// Get statistics about the trust graph
  Map<String, dynamic> getTrustStatistics() {
    final totalNodes = _trustGraph.length;
    final totalEdges = _trustGraph.values.fold(0, (sum, connections) => sum + connections.length);
    final averageConnections = totalNodes > 0 ? totalEdges / totalNodes : 0.0;
    
    return {
      'total_nodes': totalNodes,
      'total_edges': totalEdges,
      'average_connections': averageConnections,
      'reputation_range': {
        'min': _reputationScores.values.isEmpty ? 0.0 : _reputationScores.values.reduce(math.min),
        'max': _reputationScores.values.isEmpty ? 0.0 : _reputationScores.values.reduce(math.max),
        'average': _reputationScores.values.isEmpty ? 0.0 : _reputationScores.values.reduce(0, (sum, score) => sum + score) / _reputationScores.length,
      },
    };
  }
}

/// Queue entry for BFS traversal
class QueueEntry {
  final String nodeId;
  final int depth;
  
  const QueueEntry(this.nodeId, this.depth);
}
