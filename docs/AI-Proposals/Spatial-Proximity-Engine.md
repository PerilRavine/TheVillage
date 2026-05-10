# Technical Implementation: Spatial Proximity Engine (Dart Frontend)

## Architecture Overview

The Spatial Proximity Engine manages 3D spatial relationships between villages and users, implementing force-directed graph layouts for the Map and trust-based proximity filtering for village spaces.

## Core Engine Interface

```dart
// lib/domain/services/spatial_proximity_engine.dart
abstract class SpatialProximityEngine {
  // Map functionality
  Future<MapLayout> calculateVillagePositions(List<Village> villages);
  Stream<MapLayout> getMapLayoutUpdates();
  void updateVillageRelationship(Village village1, Village village2, double strength);
  
  // Village space functionality
  Future<List<User>> getVisibleUsers(User currentUser, VillageSpace space);
  Future<List<Resource>> getVisibleResources(User currentUser, VillageSpace space);
  bool isWithinThreeDegrees(User user1, User user2);
  
  // 3D rendering integration
  void initializeRenderer(CanvasElement canvas);
  void renderMap(MapLayout layout);
  void renderVillageSpace(VillageSpace space, List<SpatialEntity> entities);
}
```

## Force-Directed Graph Algorithm

```dart
// lib/infrastructure/spatial/force_directed_graph.dart
class ForceDirectedGraph {
  static const double DEFAULT_SPRING_LENGTH = 100.0;
  static const double DEFAULT_SPRING_STRENGTH = 0.1;
  static const double DEFAULT_REPULSION = 1000.0;
  static const double DEFAULT_DAMPING = 0.9;
  static const int MAX_ITERATIONS = 1000;
  static const double THRESHOLD = 0.01;
  
  List<VillageNode> _nodes = [];
  List<VillageEdge> _edges = [];
  Random _random = Random();
  
  Future<MapLayout> calculateLayout(List<Village> villages, List<VillageRelationship> relationships) async {
    _initializeGraph(villages, relationships);
    _randomizePositions();
    
    for (int iteration = 0; iteration < MAX_ITERATIONS; iteration++) {
      final forces = _calculateForces();
      final totalMovement = _applyForces(forces);
      
      if (totalMovement < THRESHOLD) break;
      
      // Emit intermediate results for smooth animation
      if (iteration % 10 == 0) {
        yield _createLayout();
      }
    }
    
    return _createLayout();
  }
  
  void _initializeGraph(List<Village> villages, List<VillageRelationship> relationships) {
    _nodes = villages.map((v) => VillageNode(
      id: v.id,
      village: v,
      position: Vector3.zero(),
      velocity: Vector3.zero(),
      mass: v.population.toDouble() + 1.0, // Population affects mass
    )).toList();
    
    _edges = relationships.map((r) => VillageEdge(
      source: _nodes.firstWhere((n) => n.id == r.village1Id),
      target: _nodes.firstWhere((n) => n.id == r.village2Id),
      strength: r.strength, // Relationship strength (0.0 to 1.0)
      restLength: DEFAULT_SPRING_LENGTH * (1.0 - r.strength), // Stronger relationships = shorter rest length
    )).toList();
  }
  
  Map< VillageNode, Vector3> _calculateForces() {
    final forces = <VillageNode, Vector3>{};
    
    // Initialize forces to zero
    for (final node in _nodes) {
      forces[node] = Vector3.zero();
    }
    
    // Calculate spring forces (attraction/repulsion between connected villages)
    for (final edge in _edges) {
      final direction = edge.target.position - edge.source.position;
      final distance = direction.length;
      
      if (distance > 0) {
        final normalizedDirection = direction.normalized();
        
        // Hooke's law: F = -k * (x - restLength)
        final springForce = DEFAULT_SPRING_STRENGTH * edge.strength * (distance - edge.restLength);
        final forceVector = normalizedDirection * springForce;
        
        forces[edge.source]!.add(forceVector);
        forces[edge.target]!.sub(forceVector);
      }
    }
    
    // Calculate repulsion forces between all villages (to prevent overlap)
    for (int i = 0; i < _nodes.length; i++) {
      for (int j = i + 1; j < _nodes.length; j++) {
        final node1 = _nodes[i];
        final node2 = _nodes[j];
        
        final direction = node2.position - node1.position;
        final distance = direction.length;
        
        if (distance > 0 && distance < 200.0) { // Only repel if close
          final normalizedDirection = direction.normalized();
          
          // Coulomb's law: F = k / r^2
          final repulsionForce = DEFAULT_REPULSION / (distance * distance);
          final forceVector = normalizedDirection * repulsionForce;
          
          forces[node1]!.sub(forceVector);
          forces[node2]!.add(forceVector);
        }
      }
    }
    
    // Add centering force to keep villages clustered
    final center = Vector3.zero();
    for (final node in _nodes) {
      final toCenter = center - node.position;
      forces[node]!.add(toCenter * 0.01);
    }
    
    return forces;
  }
  
  double _applyForces(Map<VillageNode, Vector3> forces) {
    double totalMovement = 0.0;
    
    for (final node in _nodes) {
      final force = forces[node]!;
      
      // Apply damping to velocity
      node.velocity = node.velocity * DEFAULT_DAMPING + force;
      
      // Update position
      node.position += node.velocity;
      
      // Track total movement for convergence detection
      totalMovement += force.length;
    }
    
    return totalMovement;
  }
  
  void _randomizePositions() {
    for (final node in _nodes) {
      node.position = Vector3(
        _random.nextDouble() * 400.0 - 200.0,
        _random.nextDouble() * 400.0 - 200.0,
        0.0, // Keep villages on same plane for now
      );
    }
  }
  
  MapLayout _createLayout() {
    return MapLayout(
      villagePositions: Map.fromEntries(
        _nodes.map((node) => MapEntry(node.id, node.position.clone()))
      ),
      relationships: _edges.map((edge) => VillageRelationshipData(
        village1Id: edge.source.id,
        village2Id: edge.target.id,
        strength: edge.strength,
      )).toList(),
      timestamp: DateTime.now(),
    );
  }
}

class VillageNode {
  String id;
  Village village;
  Vector3 position;
  Vector3 velocity;
  double mass;
  
  VillageNode({
    required this.id,
    required this.village,
    required this.position,
    required this.velocity,
    required this.mass,
  });
}

class VillageEdge {
  VillageNode source;
  VillageNode target;
  double strength;
  double restLength;
  
  VillageEdge({
    required this.source,
    required this.target,
    required this.strength,
    required this.restLength,
  });
}
```

## 3D Map Visualization

```dart
// lib/infrastructure/3d/map_visualizer.dart
class Map3DVisualizer {
  late ThreeJSRenderer _renderer;
  late Scene _scene;
  late Camera _camera;
  late Map<String, Mesh> _villageMeshes;
  late Map<String, Line> _relationshipLines;
  
  void initialize(CanvasElement canvas) {
    _renderer = ThreeJSRenderer(canvas: canvas);
    _scene = Scene();
    _camera = PerspectiveCamera(75, canvas.width / canvas.height, 0.1, 1000);
    
    _camera.position.z = 500;
    
    _setupLighting();
    _villageMeshes = {};
    _relationshipLines = {};
  }
  
  void renderMap(MapLayout layout) {
    _clearScene();
    _createVillageNodes(layout);
    _createRelationshipLines(layout);
    _renderer.render(_scene, _camera);
  }
  
  void _createVillageNodes(MapLayout layout) {
    for (final entry in layout.villagePositions.entries) {
      final villageId = entry.key;
      final position = entry.value;
      
      // Create village sphere with size based on population
      final geometry = SphereGeometry(
        radius: 20.0 + layout.getVillagePopulation(villageId) / 10.0,
        widthSegments: 32,
        heightSegments: 16,
      );
      
      final material = StandardMaterial(
        color: _getVillageColor(villageId),
        emissive: Color(0x222222),
        emissiveIntensity: 0.2,
      );
      
      final mesh = Mesh(geometry, material);
      mesh.position.setFrom(position);
      
      // Add village label
      _addVillageLabel(mesh, villageId);
      
      _villageMeshes[villageId] = mesh;
      _scene.add(mesh);
    }
  }
  
  void _createRelationshipLines(MapLayout layout) {
    for (final relationship in layout.relationships) {
      final pos1 = layout.villagePositions[relationship.village1Id]!;
      final pos2 = layout.villagePositions[relationship.village2Id]!;
      
      final geometry = BufferGeometry();
      final positions = Float32Array.fromList([
        pos1.x, pos1.y, pos1.z,
        pos2.x, pos2.y, pos2.z,
      ]);
      
      geometry.setAttribute('position', BufferAttribute(positions, 3));
      
      final material = LineBasicMaterial(
        color: _getRelationshipColor(relationship.strength),
        opacity: 0.3 + relationship.strength * 0.7,
        transparent: true,
      );
      
      final line = Line(geometry, material);
      _relationshipLines['${relationship.village1Id}-${relationship.village2Id}'] = line;
      _scene.add(line);
    }
  }
  
  Color _getVillageColor(String villageId) {
    // Color based on village reputation or type
    return Color(0x4CAF50); // Default green
  }
  
  Color _getRelationshipColor(double strength) {
    // Gradient from red (weak) to green (strong)
    final hue = strength * 120.0; // 0 (red) to 120 (green)
    return Color.fromHSV(hue, 0.7, 0.8);
  }
  
  void _addVillageLabel(Mesh mesh, String villageId) {
    // Implementation for adding 3D text labels
    // Could use CSS sprites or 3D text geometry
  }
  
  void _setupLighting() {
    final ambientLight = AmbientLight(0x404040, 0.6);
    _scene.add(ambientLight);
    
    final directionalLight = DirectionalLight(0xffffff, 0.8);
    directionalLight.position.setValues(1, 1, 0.5);
    _scene.add(directionalLight);
  }
  
  void _clearScene() {
    for (final mesh in _villageMeshes.values) {
      _scene.remove(mesh);
      mesh.geometry.dispose();
      (mesh.material as Material).dispose();
    }
    
    for (final line in _relationshipLines.values) {
      _scene.remove(line);
      line.geometry.dispose();
      (line.material as Material).dispose();
    }
    
    _villageMeshes.clear();
    _relationshipLines.clear();
  }
}
```

## 3 Degrees of Separation Engine

```dart
// lib/domain/services/three_degrees_engine.dart
class ThreeDegreesEngine {
  final TrustGraphService _trustGraph;
  final ReputationService _reputationService;
  
  ThreeDegreesEngine(this._trustGraph, this._reputationService);
  
  Future<List<User>> getVisibleUsers(User currentUser, VillageSpace space) async {
    final allUsers = await space.getAllUsers();
    final visibleUsers = <User>[];
    
    for (final user in allUsers) {
      if (user.id == currentUser.id) continue;
      
      if (await isWithinThreeDegrees(currentUser, user)) {
        visibleUsers.add(user);
      }
    }
    
    return visibleUsers;
  }
  
  Future<List<Resource>> getVisibleResources(User currentUser, VillageSpace space) async {
    final allResources = await space.getAllResources();
    final visibleResources = <Resource>[];
    
    for (final resource in allResources) {
      final owner = await resource.getOwner();
      
      // Resource is visible if owner is within 3 degrees
      if (await isWithinThreeDegrees(currentUser, owner)) {
        visibleResources.add(resource);
      }
    }
    
    return visibleResources;
  }
  
  Future<bool> isWithinThreeDegrees(User user1, User user2) async {
    // Use BFS to find shortest path in trust graph
    final queue = <User>[user1];
    final visited = <String>{user1.id};
    int degree = 0;
    
    while (queue.isNotEmpty && degree < 3) {
      final currentLevelSize = queue.length;
      
      for (int i = 0; i < currentLevelSize; i++) {
        final currentUser = queue.removeAt(0);
        
        if (currentUser.id == user2.id) {
          return true;
        }
        
        // Get all trusted connections for current user
        final trustedConnections = await _trustGraph.getTrustedConnections(currentUser);
        
        for (final connection in trustedConnections) {
          if (!visited.contains(connection.id)) {
            visited.add(connection.id);
            queue.add(connection);
          }
        }
      }
      
      degree++;
    }
    
    return false;
  }
  
  Future<int> getDegreeOfSeparation(User user1, User user2) async {
    final queue = <User>[user1];
    final visited = <String>{user1.id};
    int degree = 0;
    
    while (queue.isNotEmpty) {
      final currentLevelSize = queue.length;
      
      for (int i = 0; i < currentLevelSize; i++) {
        final currentUser = queue.removeAt(0);
        
        if (currentUser.id == user2.id) {
          return degree;
        }
        
        final trustedConnections = await _trustGraph.getTrustedConnections(currentUser);
        
        for (final connection in trustedConnections) {
          if (!visited.contains(connection.id)) {
            visited.add(connection.id);
            queue.add(connection);
          }
        }
      }
      
      degree++;
    }
    
    return -1; // No connection found
  }
}
```

## Village Space 3D Interface

```dart
// lib/infrastructure/3d/village_space_visualizer.dart
class VillageSpaceVisualizer {
  late ThreeJSRenderer _renderer;
  late Scene _scene;
  late Camera _camera;
  final ThreeDegreesEngine _threeDegreesEngine;
  final User _currentUser;
  
  Map<String, SpatialEntity> _entities = {};
  Map<String, Mesh> _entityMeshes = {};
  
  VillageSpaceVisualizer(this._threeDegreesEngine, this._currentUser);
  
  void initialize(CanvasElement canvas) {
    _renderer = ThreeJSRenderer(canvas: canvas);
    _scene = Scene();
    _camera = PerspectiveCamera(75, canvas.width / canvas.height, 0.1, 1000);
    
    _setupEnvironment();
    _setupLighting();
  }
  
  Future<void> renderVillageSpace(VillageSpace space) async {
    await _loadEntities(space);
    _updateEntityVisibility();
    _createEntityMeshes();
    _renderer.render(_scene, _camera);
  }
  
  Future<void> _loadEntities(VillageSpace space) async {
    _entities.clear();
    
    // Load visible users
    final visibleUsers = await _threeDegreesEngine.getVisibleUsers(_currentUser, space);
    for (final user in visibleUsers) {
      _entities[user.id] = SpatialEntity.user(user, await _getUserPosition(user));
    }
    
    // Load visible resources
    final visibleResources = await _threeDegreesEngine.getVisibleResources(_currentUser, space);
    for (final resource in visibleResources) {
      _entities[resource.id] = SpatialEntity.resource(resource, await _getResourcePosition(resource));
    }
  }
  
  Future<void> _updateEntityVisibility() async {
    for (final entity in _entities.values) {
      switch (entity.type) {
        case EntityType.user:
          final user = entity.data as User;
          entity.isVisible = await _threeDegreesEngine.isWithinThreeDegrees(_currentUser, user);
          entity.reputationLevel = await _reputationService.getContextualReputation(user, Context.village(space.village));
          break;
        case EntityType.resource:
          final resource = entity.data as Resource;
          final owner = await resource.getOwner();
          entity.isVisible = await _threeDegreesEngine.isWithinThreeDegrees(_currentUser, owner);
          entity.trustLevel = await _calculateResourceTrust(resource);
          break;
      }
    }
  }
  
  void _createEntityMeshes() {
    _clearEntityMeshes();
    
    for (final entity in _entities.values) {
      if (!entity.isVisible) continue;
      
      final mesh = _createEntityMesh(entity);
      _entityMeshes[entity.id] = mesh;
      _scene.add(mesh);
    }
  }
  
  Mesh _createEntityMesh(SpatialEntity entity) {
    Mesh mesh;
    
    switch (entity.type) {
      case EntityType.user:
        mesh = _createUserMesh(entity);
        break;
      case EntityType.resource:
        mesh = _createResourceMesh(entity);
        break;
    }
    
    mesh.position.setFrom(entity.position);
    return mesh;
  }
  
  Mesh _createUserMesh(SpatialEntity entity) {
    final user = entity.data as User;
    
    // Avatar size based on reputation
    final radius = 10.0 + (entity.reputationLevel / 100.0) * 15.0;
    final geometry = SphereGeometry(radius: radius);
    
    // Color based on role
    final color = _getUserRoleColor(user.role);
    final material = StandardMaterial(
      color: color,
      emissive: color * 0.3,
      emissiveIntensity: 0.5,
    );
    
    final mesh = Mesh(geometry, material);
    
    // Add reputation glow effect
    if (entity.reputationLevel > 80) {
      _addReputationGlow(mesh, entity.reputationLevel);
    }
    
    return mesh;
  }
  
  Mesh _createResourceMesh(SpatialEntity entity) {
    final resource = entity.data as Resource;
    
    // Different shapes for different resource types
    Geometry geometry;
    switch (resource.type) {
      case ResourceType.tool:
        geometry = BoxGeometry(width: 15, height: 10, depth: 5);
        break;
      case ResourceType.knowledge:
        geometry = OctahedronGeometry(radius: 12);
        break;
      case ResourceType.service:
        geometry = CylinderGeometry(radiusTop: 8, radiusBottom: 12, height: 15);
        break;
      default:
        geometry = SphereGeometry(radius: 10);
    }
    
    // Color based on trust level
    final color = _getTrustColor(entity.trustLevel);
    final material = StandardMaterial(
      color: color,
      metalness: 0.3,
      roughness: 0.4,
    );
    
    return Mesh(geometry, material);
  }
  
  void _addReputationGlow(Mesh mesh, double reputationLevel) {
    final glowGeometry = SphereGeometry(radius: mesh.geometry.boundingSphere!.radius * 1.5);
    final glowMaterial = MeshBasicMaterial(
      color: Color(0xFFFFD700),
      transparent: true,
      opacity: 0.2 + (reputationLevel / 100.0) * 0.3,
    );
    
    final glow = Mesh(glowGeometry, glowMaterial);
    mesh.add(glow);
  }
  
  Color _getUserRoleColor(Role role) {
    switch (role) {
      case Role.stranger:
        return Color(0x9E9E9E); // Gray
      case Role.sojourner:
        return Color(0x2196F3); // Blue
      case Role.denizen:
        return Color(0x4CAF50); // Green
      case Role.steward:
        return Color(0xFF9800); // Orange
      case Role.elder:
        return Color(0x9C27B0); // Purple
      case Role.reeve:
        return Color(0xF44336); // Red
      case Role.high_reeve:
        return Color(0xFFD700); // Gold
    }
  }
  
  Color _getTrustColor(double trustLevel) {
    // Gradient from red (low trust) to green (high trust)
    final hue = trustLevel * 120.0; // 0 (red) to 120 (green)
    return Color.fromHSV(hue, 0.7, 0.8);
  }
  
  void _setupEnvironment() {
    // Create skybox or environment for village space
    final environmentTexture = _loadEnvironmentTexture();
    _scene.background = environmentTexture;
    
    // Add ground plane
    final groundGeometry = PlaneGeometry(width: 500, height: 500);
    final groundMaterial = StandardMaterial(
      color: Color(0x3E2723), // Brown
      roughness: 0.8,
      metalness: 0.2,
    );
    final ground = Mesh(groundGeometry, groundMaterial);
    ground.rotation.x = -pi / 2;
    _scene.add(ground);
  }
  
  void _setupLighting() {
    final ambientLight = AmbientLight(0x404040, 0.4);
    _scene.add(ambientLight);
    
    final directionalLight = DirectionalLight(0xffffff, 0.8);
    directionalLight.position.setValues(50, 100, 50);
    _scene.add(directionalLight);
    
    // Add point lights for atmosphere
    final pointLight1 = PointLight(0xFFD700, 0.5, 100);
    pointLight1.position.setValues(0, 50, 0);
    _scene.add(pointLight1);
  }
  
  void _clearEntityMeshes() {
    for (final mesh in _entityMeshes.values) {
      _scene.remove(mesh);
      mesh.geometry.dispose();
      (mesh.material as Material).dispose();
    }
    _entityMeshes.clear();
  }
}
```

## Supporting Models

```dart
// lib/domain/models/spatial_entity.dart
enum EntityType { user, resource }

class SpatialEntity {
  String id;
  EntityType type;
  dynamic data; // User or Resource
  Vector3 position;
  bool isVisible;
  double reputationLevel;
  double trustLevel;
  
  SpatialEntity.user(User user, this.position) 
    : id = user.id,
      type = EntityType.user,
      data = user,
      isVisible = false,
      reputationLevel = 0.0,
      trustLevel = 0.0;
      
  SpatialEntity.resource(Resource resource, this.position)
    : id = resource.id,
      type = EntityType.resource,
      data = resource,
      isVisible = false,
      reputationLevel = 0.0,
      trustLevel = 0.0;
}

class MapLayout {
  Map<String, Vector3> villagePositions;
  List<VillageRelationshipData> relationships;
  DateTime timestamp;
  
  MapLayout({
    required this.villagePositions,
    required this.relationships,
    required this.timestamp,
  });
  
  double getVillagePopulation(String villageId) {
    // Implementation to get village population
    return 50.0; // Default
  }
}

class VillageRelationshipData {
  String village1Id;
  String village2Id;
  double strength;
  
  VillageRelationshipData({
    required this.village1Id,
    required this.village2Id,
    required this.strength,
  });
}
```

## Performance Optimizations

```dart
// lib/infrastructure/spatial/performance_optimizer.dart
class SpatialPerformanceOptimizer {
  static const int MAX_VISIBLE_ENTITIES = 100;
  static const double LOD_DISTANCE_THRESHOLD = 200.0;
  static const int UPDATE_RATE_MS = 100; // 10 FPS for spatial updates
  
  Timer? _updateTimer;
  final Set<String> _visibleEntityIds = {};
  
  void startOptimizedUpdates(SpatialProximityEngine engine) {
    _updateTimer = Timer.periodic(Duration(milliseconds: UPDATE_RATE_MS), (_) {
      _performOptimizedUpdate(engine);
    });
  }
  
  void _performOptimizedUpdate(SpatialProximityEngine engine) {
    // Only update entities that are visible or recently changed
    final entitiesToUpdate = _getEntitiesToUpdate();
    
    for (final entityId in entitiesToUpdate) {
      engine.updateEntity(entityId);
    }
  }
  
  Set<String> _getEntitiesToUpdate() {
    // Implement culling and prioritization logic
    return _visibleEntityIds.take(MAX_VISIBLE_ENTITIES).toSet();
  }
  
  void stopOptimizedUpdates() {
    _updateTimer?.cancel();
    _updateTimer = null;
  }
}
```

This Spatial Proximity Engine provides a comprehensive solution for 3D spatial relationships in The Village, implementing force-directed graph layouts for the Map and trust-based filtering for village spaces while maintaining performance for large-scale networks.
