# Technical Proposal: 3D UI Architecture for The Village

## Executive Summary

This proposal outlines a comprehensive 3D UI architecture for The Village's web-based operating system, implementing Gateway, Map, Village spaces, and Domicile environments using Dart/WebAssembly with WebGL rendering. The system integrates reputation visualization and role-based access control while maintaining Clean Architecture principles.

## Architecture Overview

### 1. Rendering Engine

**Technology Choice**: Native Dart 3D Engine with Skwasm Renderer

**Reasoning**:
- **Native Dart Wasm** provides maximum security and performance for WasmGC
- **Skwasm renderer** eliminates heavy JS-based rendering engines
- **Clean Room compliance** removes external JavaScript dependencies
- **WebAssembly optimization** for 60fps rendering with minimal memory footprint
- **Type safety** through pure Dart implementation without interop overhead

**Performance Targets**:
- **Frame rate**: 60fps sustained on devices with WasmGC support
- **Draw calls**: <1000 per frame through native Dart rendering
- **Memory usage**: <100MB for complete village scene (50% reduction)
- **Load time**: <2 seconds initial load, <300ms scene transitions

### 2. Scene Management System

**Technology Choice**: Native Dart Scene Graph with Spatial Partitioning

**Reasoning**:
- **Native Dart scene graph** provides optimal performance for WasmGC environment
- **Spatial partitioning** (Grid-based) enables efficient frustum culling and collision detection
- **Component-based design** allows modular addition of behaviors (reputation, interaction, etc.)
- **Memory efficiency** through data-oriented design patterns with WasmGC optimization
- **Scalability** supports 1000+ concurrent users in a single village scene

**Scene Graph Implementation**:
```dart
// lib/core/3d/scene_graph.dart
class SceneGraph {
  final List<SceneNode> _nodes = [];
  final SpatialGrid _spatialGrid = SpatialGrid(cellSize: 100.0);
  
  void update(double deltaTime) {
    _spatialGrid.update();
    for (final node in _nodes) {
      node.update(deltaTime);
    }
  }
}

class VillageNode extends SceneNode {
  @override
  void update(double deltaTime) {
    // Cull entities outside view frustum
    // Batch render similar materials
    // Apply reputation-based visual effects
  }
}

class ReputationSystem extends System {
  @override
  void update(double deltaTime, List<Entity> entities) {
    // Update visual reputation indicators
    // Apply role-based appearance changes
  }
}
```

### 3. Space Architecture

**Gateway Space**: Public landing area with village showcases
- **Layout**: Central plaza with surrounding village portals
- **Interactions**: Browse villages, meet strangers, receive vouches
- **Performance**: Optimized for 100+ concurrent visitors

**Map Space**: 3D visualization of village network
- **Layout**: Force-directed graph with village nodes
- **Dynamics**: Real-time position updates based on village relationships
- **Interactions**: Navigate between villages, view reputation networks

**Village Space**: Themed 3D environment for community interaction
- **Layout**: Modular design with Bulletin Board, Hall, Commons, Guild Hall
- **Capacity**: Limited to 150 active users (3 degrees of separation)
- **Dynamics**: Role-based access to different areas

**Domicile Space**: Personal customizable environment
- **Features**: Multi-village access, content creation tools, social feeds
- **Privacy**: Individual space with controlled access permissions

### 4. Reputation Visualization System

**Technology Choice**: Native Dart Shader System + WasmGC Optimized Effects

**Reasoning**:
- **Native Dart shaders** eliminate JavaScript overhead for visual effects
- **WasmGC optimization** provides smooth reputation animations with minimal memory
- **GPU acceleration** through Skwasm shader pipeline
- **Clean Room compliance** removes external graphics dependencies
- **Accessibility**: Color-blind friendly patterns with native Dart rendering

**Implementation Details**:
```dart
// lib/ui/reputation/visualizer.dart
class ReputationVisualizer {
  final SkwasmShader _reputationShader;
  final WasmParticleEffect _particleEffect;
  
  void updateReputationVisual(Entity entity, double oldScore, double newScore) {
    if (newScore > oldScore) {
      _particleEffect.emitSuccess(entity.position);
    } else {
      _particleEffect.emitPenalty(entity.position);
    }
    
    _reputationShader.setUniform('reputationScore', newScore);
    _reputationShader.setUniform('reputationColor', _calculateReputationColor(newScore));
  }
  
  Color _calculateReputationColor(double score) {
    // Color-blind friendly gradient using native Dart color space
    return Color.lerp(Colors.red, Colors.green, score / 100.0);
  }
}
```

**Visual Features**:
- **WasmGC-optimized reputation auras** around user avatars
- **Native particle effects** for reputation changes (gains/losses)
- **Skwasm shader gradients** for reputation levels
- **Smooth transitions** between reputation states
- **Accessibility mode** with shape-based indicators

**Performance Considerations**:
- **WasmGC pooling** for particle effects
- **Native Dart instancing** for reputation indicators
- **Skwasm shader uniforms** for real-time updates
- **Memory-efficient particle systems** with automatic cleanup

### Clean Architecture Implementation

### Frontend (Dart/Wasm)

**Domain Layer**:
```dart
// lib/domain/entities/3d_space.dart
abstract class Space3D {
  String get id;
  SpaceType get type;
  List<User> get occupants;
  
  void addUser(User user);
  void removeUser(User user);
  bool canAccess(User user);
}

class VillageSpace extends Space3D {
  @override
  SpaceType get type => SpaceType.village;
  
  @override
  bool canAccess(User user) {
    return user.hasVillageAccess(this.id);
  }
}
```

**Application Layer**:
```dart
// lib/application/use_cases/space_navigation.dart
class SpaceNavigationUseCase {
  final Space3DRepository _repository;
  
  Future<List<VillageSpace>> getAccessibleVillages(User user) {
    return _repository.findVillagesWithinThreeDegrees(user);
  }
  
  Future<void> navigateToSpace(User user, Space3D space) {
    if (!space.canAccess(user)) {
      throw AccessDeniedException();
    }
    
    await _repository.recordSpaceEntry(user, space);
  }
}
```

**Infrastructure Layer**:
```dart
// lib/infrastructure/repositories/space_repository.dart
class Space3DRepositoryImpl implements Space3DRepository {
  final P2PNetwork _network;
  final LocalCache _cache;
  
  @override
  Future<List<VillageSpace>> findVillagesWithinThreeDegrees(User user) {
    // Query P2P network for connected villages
    final connectedUsers = await _network.getTrustNetwork(user, maxDepth: 3);
    
    // Cache results for performance
    await _cache.store(user.id, connectedUsers);
    
    return connectedUsers
        .where((u) => u.hasVillageSpace())
        .map((u) => u.villageSpace)
        .toList();
  }
}
```

### Backend (Laravel)

**Domain Layer**:
```php
// app/Domain/Space3D/Space3DRepository.php
interface Space3DRepository {
    public function findVillagesWithinThreeDegrees(User $user): array;
    public function recordSpaceEntry(User $user, Space3D $space): void;
    public function getSpaceOccupants(Space3D $space): Collection;
}
```

**Application Layer**:
```php
// app/UseCases/SpaceNavigation.php
class SpaceNavigationService {
    public function __construct(
        private Space3DRepository $repository,
        private ReputationService $reputation,
        private P2PService $p2p
    ) {}
    
    public function navigateToSpace(User $user, Space3D $space): void {
        if (!$this->canUserAccessSpace($user, $space)) {
            throw new AccessDeniedException('Insufficient reputation or role');
        }
        
        $this->repository->recordSpaceEntry($user, $space);
        $this->p2p->broadcastUserLocation($user, $space);
    }
    
    private function canUserAccessSpace(User $user, Space3D $space): bool {
        return $this->reputation->getUserReputationInContext($user, $space) 
               >= $space->getRequiredReputation();
    }
}
```

**Infrastructure Layer**:
```php
// app/Infrastructure/Repositories/EloquentSpace3DRepository.php
class EloquentSpace3DRepository implements Space3DRepository {
    public function findVillagesWithinThreeDegrees(User $user): array {
        // Use reputation relationships to find connected villages
        $connectedUsers = DB::table('user_villages')
            ->join('reputation_transactions', 'user_villages.user_id', '=', 'reputation_transactions.user_id')
            ->where('user_villages.user_id', $user->id)
            ->where('reputation_transactions.amount', '>', 0)
            ->distinct()
            ->pluck('village_id')
            ->toArray();
            
        return Village::whereIn('id', $connectedUsers)->get()->toArray();
    }
}
```
- **Collaborative spaces** for group projects and events

## Implementation Timeline

**Phase 1** (2 weeks): Core 3D rendering engine + basic space navigation
**Phase 2** (2 weeks): Village space implementation + venue system
**Phase 3** (1 week): Reputation visualization + role-based access
**Phase 4** (1 week): Map space + force-directed layout
**Phase 5** (1 week): Domicile space + personalization features
**Phase 6** (1 week): Performance optimization + cross-platform testing

This 3D UI architecture provides an immersive, performant foundation for The Village's web-based operating system while maintaining Clean Architecture principles and seamless integration with the reputation and P2P systems.

---

**Document Created**: May 9, 2026  
**Author**: AI Assistant (Cascade)  
**Project**: The Village - 3D UI Architecture  
**Version**: 1.0
