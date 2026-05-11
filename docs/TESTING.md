# The Village - Testing Guide

## Overview

This guide covers comprehensive testing strategies for The Village P2P Trust Network, including unit tests, integration tests, stress testing, and user acceptance testing.

## Testing Environment Setup

### Local Development Testing

```bash
# Start development environment
cd /home/bruce/programming/TheVillage
docker-compose up -d

# Run database migrations
docker-compose exec village_engine php artisan migrate

# Seed test data
docker-compose exec village_engine php artisan db:seed --class=TestDataSeeder
```

### Test Database Configuration

```env
# .env.testing
APP_ENV=testing
APP_DEBUG=true
DB_CONNECTION=sqlite
DB_DATABASE=:memory:
CACHE_DRIVER=array
SESSION_DRIVER=array
QUEUE_CONNECTION=sync
```

## Unit Testing

### Backend Tests (Laravel)

```bash
# Run all tests
docker-compose exec village_engine php artisan test

# Run specific test file
docker-compose exec village_engine php artisan test tests/Feature/ReputationTest.php

# Run with coverage
docker-compose exec village_engine php artisan test --coverage
```

#### Key Test Files

1. **Reputation System Tests**
```php
// tests/Feature/ReputationTest.php
public function test_user_reputation_calculation()
{
    $user = User::factory()->create();
    $reputationService = new ReputationCalculationService();
    
    // Add vouches
    $vouches = User::factory()->count(3)->create();
    foreach ($vouches as $voucher) {
        $reputationService->addVouch($user, $voucher, 0.8);
    }
    
    $score = $reputationService->calculateUserReputation($user->id);
    $this->assertGreaterThan(0, $score);
}

public function test_integrity_decay()
{
    $user = User::factory()->create(['reputation_score' => 80.0]);
    
    // Simulate time decay
    $reputationService = new ReputationCalculationService();
    $newScore = $reputationService->applyDecay($user, 7); // 7 days
    
    $this->assertLessThan(80.0, $newScore);
}
```

2. **P2P Signaling Tests**
```php
// tests/Feature/P2PTest.php
public function test_peer_discovery_request()
{
    $user = User::factory()->create();
    $response = $this->actingAs($user)->post('/api/p2p/discovery/request', [
        'user_location' => ['lat' => 40.7128, 'lng' => -74.0060],
        'user_preferences' => ['villages' => ['tech', 'art']],
    ]);
    
    $response->assertStatus(200);
    $this->assertDatabaseHas('peer_discovery_requests', [
        'user_id' => $user->id,
    ]);
}

public function test_peer_discovery_response()
{
    $requester = User::factory()->create();
    $responder = User::factory()->create();
    
    $response = $this->actingAs($responder)->post('/api/p2p/discovery/respond', [
        'requester_id' => $requester->id,
        'responder_profile' => ['name' => 'Test User'],
        'trust_level' => 0.8,
    ]);
    
    $response->assertStatus(200);
}
```

3. **Domicile Tests**
```php
// tests/Feature/DomicileTest.php
public function test_domicile_creation()
{
    $user = User::factory()->create();
    
    $response = $this->actingAs($user)->post('/api/domiciles', [
        'name' => 'Test Domicile',
        'description' => 'A test personal space',
        'theme' => 'default',
        'is_public' => false,
    ]);
    
    $response->assertStatus(201);
    $this->assertDatabaseHas('domiciles', [
        'user_id' => $user->id,
        'name' => 'Test Domicile',
    ]);
}

public function test_content_upload()
{
    $user = User::factory()->create();
    $domicile = Domicile::factory()->create(['user_id' => $user->id]);
    
    $response = $this->actingAs($user)->post("/api/domiciles/{$domicile->id}/content", [
        'title' => 'Test Document',
        'content_type' => 'document',
        'content' => 'This is test content',
        'access_level' => 'private',
    ]);
    
    $response->assertStatus(201);
    $this->assertDatabaseHas('domicile_contents', [
        'domicile_id' => $domicile->id,
        'title' => 'Test Document',
    ]);
}

public function test_brand_tracking()
{
    $user = User::factory()->create();
    $domicile = Domicile::factory()->create(['user_id' => $user->id]);
    $content = DomicileContent::factory()->create([
        'domicile_id' => $domicile->id,
        'user_id' => $user->id,
        'title' => 'Apple iPhone Review',
        'description' => 'Great product from Apple',
    ]);
    
    $brandTracking = new BrandTracking();
    $brands = $brandTracking->extractBrands($content->title . ' ' . $content->description);
    
    $this->assertContains('Apple', $brands);
}
```

### Frontend Tests (Dart)

```bash
# Run Dart tests
cd village_ui
dart test

# Run with coverage
dart test --coverage=coverage
```

#### Key Test Files

1. **3D Rendering Tests**
```dart
// test/core/3d/scene_graph_test.dart
void main() {
  group('SceneGraph Tests', () {
    test('should add nodes correctly', () {
      final sceneGraph = SceneGraph();
      final node = VillageNode(
        id: 'test-node',
        name: 'Test Node',
        position: Vector3(0, 0, 0),
        color: Vector3(1, 0, 0),
        size: 10.0,
      );
      
      sceneGraph.addNode(node);
      expect(sceneGraph.getAllNodes().length, 1);
    });

    test('should calculate visible nodes correctly', () {
      final sceneGraph = SceneGraph();
      final node = VillageNode(
        id: 'test-node',
        name: 'Test Node',
        position: Vector3(0, 0, 0),
        color: Vector3(1, 0, 0),
        size: 10.0,
      );
      
      sceneGraph.addNode(node);
      sceneGraph.updateCamera(Vector3(0, 10, 20), 60.0);
      
      final visibleNodes = sceneGraph.getVisibleNodes();
      expect(visibleNodes.contains(node), true);
    });
  });
}
```

2. **Force-Directed Layout Tests**
```dart
// test/core/3d/force_directed_layout_test.dart
void main() {
  group('ForceDirectedLayout Tests', () {
    test('should apply spring forces correctly', () {
      final sceneGraph = SceneGraph();
      final layout = ForceDirectedLayout(sceneGraph);
      
      final node1 = VillageNode(id: '1', name: 'Node 1', position: Vector3(0, 0, 0), color: Vector3(1, 0, 0), size: 10.0);
      final node2 = VillageNode(id: '2', name: 'Node 2', position: Vector3(100, 0, 0), color: Vector3(0, 1, 0), size: 10.0);
      
      sceneGraph.addNode(node1);
      sceneGraph.addNode(node2);
      sceneGraph.addEdge(SceneEdge(id: 'edge', node1: node1, node2: node2));
      
      layout.update(0.016); // 60 FPS
      
      // Nodes should move closer due to spring force
      expect(node1.position.x, greaterThan(0));
      expect(node2.position.x, lessThan(100));
    });
  });
}
```

## Integration Testing

### API Integration Tests

```bash
# Run integration tests
docker-compose exec village_engine php artisan test tests/Integration/
```

#### Key Integration Tests

1. **End-to-End P2P Flow**
```php
// tests/Integration/P2PIntegrationTest.php
public function test_complete_p2p_handshake()
{
    // Create users
    $stranger = User::factory()->create();
    $denizen = User::factory()->create(['reputation_score' => 80.0]);
    
    // Stranger requests discovery
    $response = $this->actingAs($stranger)->post('/api/p2p/discovery/request', [
        'user_location' => ['lat' => 40.7128, 'lng' => -74.0060],
        'user_preferences' => ['villages' => ['tech']],
    ]);
    
    $response->assertStatus(200);
    
    // Denizen responds
    $response = $this->actingAs($denizen)->post('/api/p2p/discovery/respond', [
        'requester_id' => $stranger->id,
        'responder_profile' => ['name' => 'Denizen User'],
        'trust_level' => 0.8,
    ]);
    
    $response->assertStatus(200);
    
    // Verify connection established
    $this->assertDatabaseHas('peer_connections', [
        'user_id' => $stranger->id,
        'peer_id' => $denizen->id,
    ]);
}
```

2. **Domicile Content Flow**
```php
// tests/Integration/DomicileIntegrationTest.php
public function test_content_sharing_flow()
{
    $user = User::factory()->create();
    $domicile = Domicile::factory()->create(['user_id' => $user->id]);
    
    // Upload content
    $response = $this->actingAs($user)->post("/api/domiciles/{$domicile->id}/content", [
        'title' => 'Apple Product Review',
        'content_type' => 'document',
        'content' => 'This is a great Apple product',
        'access_level' => 'public',
    ]);
    
    $response->assertStatus(201);
    
    // Verify brand tracking
    $this->assertDatabaseHas('brand_tracking', [
        'brand_name' => 'Apple',
        'domicile_id' => $domicile->id,
    ]);
    
    // Verify analytics recorded
    $this->assertDatabaseHas('domicile_analytics', [
        'domicile_id' => $domicile->id,
    ]);
}
```

### Frontend Integration Tests

```bash
# Run frontend integration tests
cd village_ui
dart test integration/
```

#### Key Integration Tests

1. **WebSocket Connection Tests**
```dart
// test/integration/websocket_test.dart
void main() {
  group('WebSocket Integration Tests', () {
    test('should connect to Reverb successfully', () async {
      final echo = Echo();
      
      // Connect to WebSocket
      echo.connect('ws://localhost:8080/app/reverb_key');
      
      // Wait for connection
      await Future.delayed(Duration(seconds: 2));
      
      expect(echo.connector.socket.connected, true);
      
      echo.disconnect();
    });

    test('should receive peer discovery events', () async {
      final echo = Echo();
      bool eventReceived = false;
      
      echo.connect('ws://localhost:8080/app/reverb_key');
      
      // Listen for peer discovery events
      echo.private('peer-discovery.123')
          .listen('PeerDiscoveryRequested', (event) {
        eventReceived = true;
      });
      
      // Trigger event
      await Future.delayed(Duration(seconds: 2));
      
      expect(eventReceived, true);
      
      echo.disconnect();
    });
  });
}
```

## Stress Testing

### 5-User Stress Test

```bash
# Run stress test
docker-compose exec village_engine php artisan test:stress --users=5 --duration=300
```

#### Stress Test Scenarios

1. **Concurrent P2P Signaling**
```php
// tests/Stress/P2PStressTest.php
public function test_concurrent_peer_discovery()
{
    $users = User::factory()->count(5)->create();
    $responses = [];
    
    // Simulate concurrent discovery requests
    foreach ($users as $user) {
        $response = $this->actingAs($user)->post('/api/p2p/discovery/request', [
            'user_location' => ['lat' => 40.7128, 'lng' => -74.0060],
            'user_preferences' => ['villages' => ['tech', 'art']],
        ]);
        $responses[] = $response->status();
    }
    
    // All requests should succeed
    foreach ($responses as $status) {
        $this->assertEquals(200, $status);
    }
    
    // Verify performance metrics
    $this->assertLessThan(1000, microtime(true) - $this->startTime);
}
```

2. **Content Upload Stress Test**
```php
// tests/Stress/DomicileStressTest.php
public function test_concurrent_content_uploads()
{
    $users = User::factory()->count(5)->create();
    $domiciles = [];
    
    // Create domiciles
    foreach ($users as $user) {
        $domicile = Domicile::factory()->create(['user_id' => $user->id]);
        $domiciles[] = $domicile;
    }
    
    $startTime = microtime(true);
    $responses = [];
    
    // Concurrent uploads
    foreach ($users as $index => $user) {
        $response = $this->actingAs($user)->post("/api/domiciles/{$domiciles[$index]->id}/content", [
            'title' => "Test Content $index",
            'content_type' => 'document',
            'content' => "Test content $index",
            'access_level' => 'public',
        ]);
        $responses[] = $response->status();
    }
    
    // Verify all uploads succeeded
    foreach ($responses as $status) {
        $this->assertEquals(201, $status);
    }
    
    // Verify performance (should complete within 2 seconds)
    $this->assertLessThan(2.0, microtime(true) - $startTime);
}
```

### Performance Benchmarks

```bash
# Run performance tests
docker-compose exec village_engine php artisan benchmark:run

# Memory usage test
docker-compose exec village_engine php artisan test:memory

# Database query performance
docker-compose exec village_engine php artisan test:database-performance
```

## User Acceptance Testing

### Test Scenarios

1. **New User Onboarding**
```bash
# Test script: scripts/test_user_onboarding.sh
#!/bin/bash

echo "Testing new user onboarding..."

# Create test user
curl -X POST http://localhost:8000/api/register \
  -H "Content-Type: application/json" \
  -d '{
    "username": "testuser",
    "email": "test@example.com",
    "password": "password123"
  }'

# Login
TOKEN=$(curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com",
    "password": "password123"
  }' | jq -r '.token')

# Create domicile
curl -X POST http://localhost:8000/api/domiciles \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "My Domicile",
    "description": "My personal space"
  }'

echo "Onboarding test completed"
```

2. **P2P Resource Sharing**
```bash
# Test script: scripts/test_p2p_sharing.sh
#!/bin/bash

echo "Testing P2P resource sharing..."

# Setup two users
USER1_TOKEN=$(get_user_token "user1@example.com")
USER2_TOKEN=$(get_user_token "user2@example.com")

# User 1 requests peer discovery
curl -X POST http://localhost:8000/api/p2p/discovery/request \
  -H "Authorization: Bearer $USER1_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "user_location": {"lat": 40.7128, "lng": -74.0060},
    "user_preferences": {"villages": ["tech"]}
  }'

# User 2 responds
curl -X POST http://localhost:8000/api/p2p/discovery/respond \
  -H "Authorization: Bearer $USER2_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "requester_id": 1,
    "responder_profile": {"name": "User 2"},
    "trust_level": 0.8
  }'

echo "P2P sharing test completed"
```

### Browser Testing

```bash
# Start frontend
cd village_ui
dart run build:wasm
python3 -m http.server 8080 --directory web

# Open browser for manual testing
open http://localhost:8080
```

#### Manual Testing Checklist

1. **3D Village Rendering**
   - [ ] Village nodes render correctly
   - [ ] Force-directed layout stabilizes
   - [ ] Camera controls work smoothly
   - [ ] 3-degree separation filtering functions

2. **P2P Signaling**
   - [ ] WebSocket connection established
   - [ ] Peer discovery requests work
   - [ ] Real-time responses received
   - [ ] Connection status updates

3. **Domicile Management**
   - [ ] Create personal space
   - [ ] Upload content successfully
   - [ ] Share with other users
   - [ ] Brand tracking detects mentions

## Automated Testing Pipeline

### GitHub Actions

```yaml
# .github/workflows/test.yml
name: Tests

on: [push, pull_request]

jobs:
  backend:
    runs-on: ubuntu-latest
    
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: thevillage_test
        ports:
          - 3306:3306
        options: --health-cmd="mysqladmin ping" --health-interval=10s --health-timeout=5s --health-retries=3
    
    steps:
    - uses: actions/checkout@v3
    
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '8.3'
        extensions: bcmath, pdo_mysql
        
    - name: Install dependencies
      run: |
        cd village_engine
        composer install --no-progress --no-suggest --prefer-dist --optimize-autoloader
        
    - name: Run tests
      run: |
        cd village_engine
        php artisan test --coverage-clover=coverage.xml
        
    - name: Upload coverage
      uses: codecov/codecov-action@v3
      with:
        file: ./village_engine/coverage.xml

  frontend:
    runs-on: ubuntu-latest
    
    steps:
    - uses: actions/checkout@v3
    
    - name: Setup Dart
      uses: dart-lang/setup-dart@v1
      
    - name: Install dependencies
      run: |
        cd village_ui
        dart pub get
        
    - name: Run tests
      run: |
        cd village_ui
        dart test --coverage=coverage
```

## Test Data Management

### Test Seeders

```php
// database/seeders/TestDataSeeder.php
class TestDataSeeder extends Seeder
{
    public function run()
    {
        // Create test users
        $users = User::factory()->count(10)->create();
        
        // Create villages
        $villages = Village::factory()->count(3)->create();
        
        // Create domiciles
        foreach ($users as $user) {
            Domicile::factory()->create(['user_id' => $user->id]);
        }
        
        // Create content with brand mentions
        foreach ($users as $user) {
            DomicileContent::factory()->count(5)->create([
                'user_id' => $user->id,
                'title' => 'Review of Apple Products',
                'description' => 'Great products from Apple and Google',
            ]);
        }
        
        // Create reputation scores
        foreach ($users as $user) {
            ReputationScore::factory()->create([
                'user_id' => $user->id,
                'score' => rand(50, 100),
            ]);
        }
    }
}
```

### Test Database Cleanup

```bash
# Reset test database
docker-compose exec village_engine php artisan migrate:fresh --seed

# Clean up test data
docker-compose exec village_engine php artisan test:cleanup
```

## Monitoring Test Results

### Test Reports

```bash
# Generate test report
docker-compose exec village_engine php artisan test:report

# View coverage report
open village_engine/storage/app/coverage/index.html
```

### Performance Metrics

```bash
# Monitor test performance
docker-compose exec village_engine php artisan test:performance

# Generate benchmark report
docker-compose exec village_engine php artisan benchmark:report
```

This comprehensive testing guide ensures The Village system is thoroughly validated across all components and scenarios before production deployment.
