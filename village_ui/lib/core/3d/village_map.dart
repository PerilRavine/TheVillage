import 'dart:math';
import 'package:vector_math/vector_math.dart';
import 'scene_graph.dart';
import 'force_directed_layout.dart';
import 'three_degree_separation.dart';

/// Main village map component
/// Combines 3D rendering with force-directed layout and 3-degree separation
class VillageMap {
  final SceneGraph _sceneGraph;
  final ForceDirectedLayout _layout;
  final ThreeDegreeSeparation _separation;
  final VillageRenderer _renderer;
  
  // UI state
  String _currentUserId = '';
  double _minReputationFilter = 0.0;
  bool _showThreeDegreeOnly = true;
  
  VillageMap(this._sceneGraph) 
    : _layout = ForceDirectedLayout(_sceneGraph),
      _separation = ThreeDegreeSeparation(_sceneGraph),
      _renderer = VillageRenderer(_sceneGraph) {
    _initializeMap();
  }
  
  /// Initialize the village map
  void _initializeMap() {
    _layout.enableSpatialPartitioning(true);
    _layout.enableLOD(true);
    _renderer.enableSpatialPartitioning(true);
    
    // Start layout simulation
    _layout.update(0.016); // 60 FPS
  }
  
  /// Update the map
  void update(double deltaTime) {
    // Update force-directed layout
    _layout.update(deltaTime);
    
    // Update 3-degree separation
    _separation.applyThreeDegreeSeparation(
      _currentUserId, 
      _minReputationFilter,
      _showThreeDegreeOnly
    );
    
    // Render the scene
    _renderer.render(deltaTime);
  }
  
  /// Set current user for filtering
  void setCurrentUser(String userId) {
    _currentUserId = userId;
  }
  
  /// Set minimum reputation filter
  void setMinReputationFilter(double minReputation) {
    _minReputationFilter = minReputation;
  }
  
  /// Toggle 3-degree separation
  void toggleThreeDegreeSeparation(bool enabled) {
    _showThreeDegreeOnly = enabled;
  }
  
  /// Get visible villages for current user
  List<VillageSpaceNode> getVisibleVillages() {
    final allNodes = _sceneGraph.getAllNodes().whereType<VillageSpaceNode>();
    
    if (_showThreeDegreeOnly) {
      final accessibleVillageIds = _separation.getAccessibleVillages(
        _currentUserId, 
        _minReputationFilter
      );
      
      return allNodes.where((node) => 
        accessibleVillageIds.contains(node.villageId)
      ).toList();
    }
    
    return allNodes.where((node) => 
      _separation.canAccessVillage(_currentUserId, node.villageId, _minReputationFilter)
    ).toList();
  }
  
  /// Get visible users for current user
  List<VillageNode> getVisibleUsers() {
    final allNodes = _sceneGraph.getAllNodes().whereType<VillageNode>();
    
    if (_showThreeDegreeOnly) {
      final accessibleUserIds = _separation.getUsersWithinThreeDegrees(
        _currentUserId, 
        _minReputationFilter
      );
      
      return allNodes.where((node) => 
        accessibleUserIds.contains(node.userId)
      ).toList();
    }
    
    return allNodes.where((node) => 
      _separation.canAccessVillage(_currentUserId, node.userId, _minReputationFilter)
    ).toList();
  }
  
  /// Navigate to a village
  void navigateToVillage(String villageId) {
    if (_separation.canAccessVillage(_currentUserId, villageId, _minReputationFilter)) {
      _focusOnVillage(villageId);
    }
  }
  
  /// Focus camera on a specific village
  void _focusOnVillage(String villageId) {
    final villageNode = _sceneGraph.getAllNodes()
      .whereType<VillageSpaceNode>()
      .firstWhere((node) => node.villageId == villageId);
    
    // Animate camera to village position
    _animateCameraTo(villageNode.position);
  }
  
  /// Animate camera to position
  void _animateCameraTo(Vector3 targetPosition) {
    // Smooth camera animation
    final startPosition = _renderer.camera.position;
    final distance = targetPosition.distanceTo(startPosition);
    final duration = (distance / 500.0).clamp(0.5, 2.0); // 500 units/second
    
    // This would be implemented with animation system
    _renderer.camera.position = targetPosition;
    _renderer.camera.lookAt(Vector3.zero());
  }
  
  /// Get map statistics
  Map<String, dynamic> getMapStatistics() {
    final totalVillages = _sceneGraph.getAllNodes()
      .whereType<VillageSpaceNode>()
      .length;
      
    final totalUsers = _sceneGraph.getAllNodes()
      .whereType<VillageNode>()
      .length;
      
    final accessibleVillages = getVisibleVillages().length;
    final accessibleUsers = getVisibleUsers().length;
    
    return {
      'total_villages': totalVillages,
      'total_users': totalUsers,
      'accessible_villages': accessibleVillages,
      'accessible_users': accessibleUsers,
      'three_degree_enabled': _showThreeDegreeOnly,
      'min_reputation_filter': _minReputationFilter,
      'layout_stabilized': _layout.isStabilized(),
      'trust_graph_stats': _separation.getTrustStatistics(),
    };
  }
  
  /// Handle user interaction with map
  void handleMapInteraction(Vector3 screenPosition, Vector3 worldPosition) {
    // Raycast to find clicked object
    final clickedNode = _raycast(worldPosition);
    
    if (clickedNode != null) {
      if (clickedNode is VillageSpaceNode) {
        _handleVillageClick(clickedNode as VillageSpaceNode);
      } else if (clickedNode is VillageNode) {
        _handleUserClick(clickedNode as VillageNode);
      }
    }
  }
  
  /// Raycast to find clicked node
  SceneNode? _raycast(Vector3 worldPosition) {
    final visibleNodes = _sceneGraph.getVisibleNodes();
    
    for (final node in visibleNodes) {
      final boundingBox = node.getBoundingBox();
      if (boundingBox.contains(worldPosition)) {
        return node;
      }
    }
    
    return null;
  }
  
  /// Handle village click
  void _handleVillageClick(VillageSpaceNode village) {
    // Show village details panel
    _showVillageDetails(village);
  }
  
  /// Handle user click
  void _handleUserClick(VillageNode user) {
    // Show user profile panel
    _showUserProfile(user);
  }
  
  /// Show village details panel
  void _showVillageDetails(VillageSpaceNode village) {
    // This would update UI with village information
    print('Village clicked: ${village.villageName}');
    print('Population: ${village.population}');
    print('Theme: ${village.theme}');
  }
  
  /// Show user profile panel
  void _showUserProfile(VillageNode user) {
    // This would update UI with user information
    print('User clicked: ${user.displayName}');
    print('Role: ${user.role}');
    print('Reputation: ${user.reputation}');
  }
  
  /// Dispose resources
  void dispose() {
    _renderer.dispose();
  }
}

/// Extension for filtering nodes by type
extension ListExtension<T> on List {
  List<T> whereType<T>() {
    return where((item) => item is T).cast<T>();
  }
}

/// Extension for bounding box
extension BoundingBoxExtension on BoundingBox {
  bool contains(Vector3 point) {
    // Simple AABB containment test
    return point.x >= min.x && point.x <= max.x &&
           point.y >= min.y && point.y <= max.y &&
           point.z >= min.z && point.z <= max.z;
  }
}
