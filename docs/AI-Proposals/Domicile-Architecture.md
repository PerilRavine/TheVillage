# Technical Implementation: Domicile Encrypted Tenant Space

## Architecture Overview

The Domicile is a private, encrypted tenant space that serves as the user's personal headquarters within The Village. It implements zero-knowledge privacy principles while providing aggregated feeds, multi-village access, and creative workstation tools.

## Core Domicile Interface

```dart
// lib/domain/services/domicile_service.dart
abstract class DomicileService {
  // Tenant management
  Future<Domicile> createDomicile(User owner);
  Future<Domicile> getDomicile(String domicileId);
  Future<bool> hasAccess(User user, String domicileId);
  
  // Encrypted data operations
  Future<void> storePrivateData(String domicileId, String key, dynamic data);
  Future<T?> getPrivateData<T>(String domicileId, String key);
  Future<void> deletePrivateData(String domicileId, String key);
  
  // Feed aggregation
  Future<List<FeedItem>> getAggregatedFeeds(String domicileId);
  Future<void> addFeedSource(String domicileId, FeedSource source);
  Future<void> removeFeedSource(String domicileId, String sourceId);
  
  // Village handshakes
  Future<List<VillageHandshake>> getActiveHandshakes(String domicileId);
  Future<VillageHandshake> initiateVillageHandshake(String domicileId, Village village);
  Future<void> terminateHandshake(String domicileId, String handshakeId);
  
  // Workstation tools
  Future<List<WorkstationProject>> getProjects(String domicileId);
  Future<WorkstationProject> createProject(String domicileId, ProjectType type);
  Future<void> updateProject(String projectId, WorkstationProject project);
  
  // Privacy controls
  Future<List<TrustAction>> getTrustActions(String domicileId);
  Future<TrustAction> createTrustAction(String domicileId, TrustActionRequest request);
  Future<bool> executeTrustAction(String actionId, UserSignature signature);
}
```

## Encrypted Tenant Space Implementation

```dart
// lib/infrastructure/domicile/encrypted_domicile.dart
class EncryptedDomicile implements DomicileService {
  final EncryptionService _encryption;
  final DomicileRepository _repository;
  final FeedAggregator _feedAggregator;
  final HandshakeGateway _handshakeGateway;
  final WorkstationManager _workstationManager;
  final PrivacyController _privacyController;
  
  EncryptedDomicile(
    this._encryption,
    this._repository,
    this._feedAggregator,
    this._handshakeGateway,
    this._workstationManager,
    this._privacyController,
  );
  
  @override
  Future<Domicile> createDomicile(User owner) async {
    // Generate unique encryption keys for this domicile
    final keyPair = await _encryption.generateKeyPair();
    final symmetricKey = await _encryption.generateSymmetricKey();
    
    // Create domicile with encrypted metadata
    final domicile = Domicile.create(
      owner: owner,
      publicKey: keyPair.publicKey,
      encryptedSymmetricKey: await _encryption.encryptWithPublicKey(
        symmetricKey, 
        keyPair.publicKey
      ),
    );
    
    await _repository.save(domicile);
    return domicile;
  }
  
  @override
  Future<void> storePrivateData(String domicileId, String key, dynamic data) async {
    final domicile = await _repository.findById(domicileId);
    final userKey = await _getUserDecryptionKey(domicile);
    
    // Encrypt data with user's symmetric key
    final encryptedData = await _encryption.encryptWithSymmetricKey(
      jsonEncode(data),
      userKey,
    );
    
    // Store with additional metadata for audit trail
    final privateData = PrivateData(
      domicileId: domicileId,
      key: key,
      encryptedData: encryptedData,
      createdAt: DateTime.now(),
      accessLog: [],
    );
    
    await _repository.storePrivateData(privateData);
  }
  
  @override
  Future<T?> getPrivateData<T>(String domicileId, String key) async {
    final domicile = await _repository.findById(domicileId);
    final userKey = await _getUserDecryptionKey(domicile);
    
    final privateData = await _repository.getPrivateData(domicileId, key);
    if (privateData == null) return null;
    
    // Log access for privacy audit
    await _logDataAccess(privateData, 'read');
    
    // Decrypt and return data
    final decryptedData = await _encryption.decryptWithSymmetricKey(
      privateData.encryptedData,
      userKey,
    );
    
    return jsonDecode(decryptedData) as T?;
  }
  
  Future<SymmetricKey> _getUserDecryptionKey(Domicile domicile) async {
    // Decrypt symmetric key with user's private key
    return await _encryption.decryptWithPrivateKey(
      domicile.encryptedSymmetricKey,
      await _getUserPrivateKey(domicile.owner),
    );
  }
  
  Future<void> _logDataAccess(PrivateData data, String action) async {
    data.accessLog.add(AccessLogEntry(
      action: action,
      timestamp: DateTime.now(),
      ipAddress: _getCurrentIpAddress(),
    ));
    await _repository.updatePrivateData(data);
  }
}
```

## Aggregated Feeds System

```dart
// lib/infrastructure/feeds/feed_aggregator.dart
class FeedAggregator {
  final Map<FeedType, FeedAdapter> _adapters;
  final FeedCache _cache;
  final PrivacyController _privacy;
  
  FeedAggregator(this._adapters, this._cache, this._privacy);
  
  Future<List<FeedItem>> getAggregatedFeeds(String domicileId) async {
    final sources = await _getFeedSources(domicileId);
    final feedItems = <FeedItem>[];
    
    for (final source in sources) {
      try {
        final adapter = _adapters[source.type];
        if (adapter != null) {
          final items = await _fetchFeedItems(source);
          
          // Filter and sanitize based on privacy settings
          final filteredItems = await _privacy.filterFeedItems(items, domicileId);
          feedItems.addAll(filteredItems);
        }
      } catch (e) {
        // Log error but continue with other sources
        _logFeedError(source, e);
      }
    }
    
    // Sort by relevance and date
    feedItems.sort((a, b) => b.relevanceScore.compareTo(a.relevanceScore));
    
    // Cache results
    await _cache.storeFeedItems(domicileId, feedItems);
    
    return feedItems.take(100).toList(); // Limit to 100 most relevant items
  }
  
  Future<List<FeedItem>> _fetchFeedItems(FeedSource source) async {
    final cachedItems = await _cache.getCachedItems(source.id);
    
    // Check if cache is still valid
    if (cachedItems != null && !cachedItems.isExpired()) {
      return cachedItems.items;
    }
    
    // Fetch fresh data
    final adapter = _adapters[source.type]!;
    final items = await adapter.fetchItems(source.config);
    
    // Cache the results
    await _cache.storeFeedItems(source.id, CachedFeedItems(items));
    
    return items;
  }
}

// lib/infrastructure/feeds/adapters/twitter_adapter.dart
class TwitterAdapter implements FeedAdapter {
  @override
  Future<List<FeedItem>> fetchItems(Map<String, dynamic> config) async {
    final apiKey = config['api_key'] as String;
    final username = config['username'] as String;
    
    // Fetch Twitter data using their API
    final response = await _twitterApi.getUserTimeline(username, apiKey);
    
    return response.tweets.map((tweet) => FeedItem(
      id: tweet.id,
      type: FeedType.twitter,
      title: tweet.text,
      content: tweet.text,
      author: tweet.author,
      publishedAt: tweet.createdAt,
      relevanceScore: _calculateRelevance(tweet),
      metadata: {
        'retweets': tweet.retweetCount,
        'likes': tweet.likeCount,
        'url': tweet.url,
      },
    )).toList();
  }
  
  double _calculateRelevance(Tweet tweet) {
    // Calculate relevance based on engagement and recency
    final engagementScore = tweet.retweetCount + tweet.likeCount;
    final recencyScore = _calculateRecencyScore(tweet.createdAt);
    
    return (engagementScore * 0.7) + (recencyScore * 0.3);
  }
}

// lib/infrastructure/feeds/adapters/news_adapter.dart
class NewsAdapter implements FeedAdapter {
  @override
  Future<List<FeedItem>> fetchItems(Map<String, dynamic> config) async {
    final apiKey = config['api_key'] as String;
    final sources = config['sources'] as List<String>;
    
    final articles = <NewsArticle>[];
    
    for (final source in sources) {
      final response = await _newsApi.getArticles(source, apiKey);
      articles.addAll(response.articles);
    }
    
    return articles.map((article) => FeedItem(
      id: article.id,
      type: FeedType.news,
      title: article.title,
      content: article.description,
      author: article.author,
      publishedAt: article.publishedAt,
      relevanceScore: _calculateRelevance(article),
      metadata: {
        'source': article.source.name,
        'url': article.url,
        'imageUrl': article.urlToImage,
      },
    )).toList();
  }
}
```

## Handshake Gateway System

```dart
// lib/infrastructure/handshake/handshake_gateway.dart
class HandshakeGateway {
  final VillageRepository _villageRepository;
  final TrustGraphService _trustGraph;
  final PrivacyController _privacy;
  
  Future<VillageHandshake> initiateVillageHandshake(String domicileId, Village village) async {
    // Verify user has access to requested village
    final domicile = await _getDomicile(domicileId);
    if (!await _canAccessVillage(domicile.owner, village)) {
      throw VillageAccessDeniedException();
    }
    
    // Create cryptographic handshake challenge
    final challenge = await _createHandshakeChallenge(domicile, village);
    
    final handshake = VillageHandshake.create(
      domicileId: domicileId,
      villageId: village.id,
      challenge: challenge,
      status: HandshakeStatus.pending,
      createdAt: DateTime.now(),
    );
    
    await _saveHandshake(handshake);
    
    // Send handshake request to village
    await _sendHandshakeRequest(handshake, village);
    
    return handshake;
  }
  
  Future<void> completeHandshake(String handshakeId, HandshakeResponse response) async {
    final handshake = await _getHandshake(handshakeId);
    
    // Verify cryptographic response
    if (!await _verifyHandshakeResponse(handshake, response)) {
      throw InvalidHandshakeResponseException();
    }
    
    // Establish secure connection
    final secureChannel = await _establishSecureChannel(handshake, response);
    
    // Update handshake status
    handshake.status = HandshakeStatus.established;
    handshake.establishedAt = DateTime.now();
    handshake.secureChannel = secureChannel;
    
    await _saveHandshake(handshake);
  }
  
  Future<void> terminateHandshake(String domicileId, String handshakeId) async {
    final handshake = await _getHandshake(handshakeId);
    
    // Verify user owns this handshake
    if (handshake.domicileId != domicileId) {
      throw UnauthorizedHandshakeAccessException();
    }
    
    // Close secure channel
    if (handshake.secureChannel != null) {
      await handshake.secureChannel!.close();
    }
    
    // Update status and log termination
    handshake.status = HandshakeStatus.terminated;
    handshake.terminatedAt = DateTime.now();
    handshake.terminationReason = 'user_initiated';
    
    await _saveHandshake(handshake);
  }
  
  Future<List<VillageHandshake>> getActiveHandshakes(String domicileId) async {
    return await _repository.getActiveHandshakes(domicileId);
  }
  
  Future<HandshakeChallenge> _createHandshakeChallenge(Domicile domicile, Village village) async {
    // Generate unique challenge for this handshake
    final challengeData = {
      'domicile_id': domicile.id,
      'village_id': village.id,
      'timestamp': DateTime.now().toIso8601String(),
      'nonce': _generateCryptographicNonce(),
    };
    
    // Sign with domicile's private key
    final signature = await _signData(challengeData, domicile.privateKey);
    
    return HandshakeChallenge(
      data: challengeData,
      signature: signature,
      publicKey: domicile.publicKey,
    );
  }
  
  Future<bool> _verifyHandshakeResponse(VillageHandshake handshake, HandshakeResponse response) async {
    // Verify response signature
    final isValidSignature = await _verifySignature(
      response.data,
      response.signature,
      handshake.villagePublicKey,
    );
    
    if (!isValidSignature) return false;
    
    // Verify response contains expected challenge data
    final responseData = response.data;
    final originalChallenge = handshake.challenge.data;
    
    return responseData['challenge'] == originalChallenge &&
           responseData['timestamp'] != null;
  }
}
```

## Workstation Tools Scaffolding

```dart
// lib/infrastructure/workstation/workstation_manager.dart
class WorkstationManager {
  final Map<ProjectType, WorkstationTool> _tools;
  final ProjectRepository _projectRepository;
  final BrandTrackingService _brandTracking;
  
  Future<WorkstationProject> createProject(String domicileId, ProjectType type) async {
    final tool = _tools[type];
    if (tool == null) {
      throw UnsupportedProjectTypeException(type);
    }
    
    // Create project with default structure
    final project = WorkstationProject.create(
      domicileId: domicileId,
      type: type,
      name: _generateDefaultProjectName(type),
      structure: await tool.getDefaultProjectStructure(),
      brandProfile: BrandProfile.empty(),
      createdAt: DateTime.now(),
    );
    
    await _projectRepository.save(project);
    
    // Initialize brand tracking for this project
    await _brandTracking.initializeTracking(project);
    
    return project;
  }
  
  Future<void> updateProject(String projectId, WorkstationProject project) async {
    final existingProject = await _projectRepository.findById(projectId);
    if (existingProject == null) {
      throw ProjectNotFoundException(projectId);
    }
    
    // Update project data
    existingProject.updateFrom(project);
    
    // Update brand tracking metrics
    await _brandTracking.updateMetrics(existingProject);
    
    await _projectRepository.save(existingProject);
  }
}

// lib/infrastructure/workstation/tools/music_tool.dart
class MusicWorkstationTool implements WorkstationTool {
  @override
  ProjectType get type => ProjectType.music;
  
  @override
  Future<ProjectStructure> getDefaultProjectStructure() async {
    return ProjectStructure.music(
      tracks: [
        Track.create(name: 'Main Melody', type: TrackType.melody),
        Track.create(name: 'Harmony', type: TrackType.harmony),
        Track.create(name: 'Rhythm', type: TrackType.rhythm),
      ],
      effects: [
        Effect.create(name: 'Reverb', type: EffectType.reverb),
        Effect.create(name: 'EQ', type: EffectType.equalizer),
      ],
      metadata: MusicMetadata(
        tempo: 120,
        key: 'C Major',
        timeSignature: '4/4',
      ),
    );
  }
  
  @override
  Future<void> initializeProject(WorkstationProject project) async {
    final musicStructure = project.structure as MusicStructure;
    
    // Create audio context and initialize tracks
    final audioContext = AudioContext();
    for (final track in musicStructure.tracks) {
      track.audioNode = await audioContext.createAudioNode(track.type);
    }
    
    // Set up brand tracking for music metrics
    await _setupMusicBrandTracking(project);
  }
  
  Future<void> _setupMusicBrandTracking(WorkstationProject project) async {
    final brandTracker = MusicBrandTracker();
    
    // Track composition metrics
    brandTracker.trackMetric('tempo_changes', project.musicMetadata.tempo);
    brandTracker.trackMetric('key_signature', project.musicMetadata.key);
    brandTracker.trackMetric('track_count', project.musicStructure.tracks.length);
    
    // Track brand-related metrics
    brandTracker.trackMetric('collaboration_count', 0);
    brandTracker.trackMetric('release_frequency', 0);
    brandTracker.trackMetric('audience_engagement', 0.0);
  }
}

// lib/infrastructure/workstation/tools/art_tool.dart
class ArtWorkstationTool implements WorkstationTool {
  @override
  ProjectType get type => ProjectType.art;
  
  @override
  Future<ProjectStructure> getDefaultProjectStructure() async {
    return ProjectStructure.art(
      canvas: Canvas.create(
        width: 1920,
        height: 1080,
        resolution: 300,
      ),
      layers: [
        Layer.create(name: 'Background', type: LayerType.background),
        Layer.create(name: 'Main Art', type: LayerType.artwork),
        Layer.create(name: 'Effects', type: LayerType.effects),
      ],
      tools: [
        ArtTool.create(name: 'Brush', type: ToolType.brush),
        ArtTool.create(name: 'Eraser', type: ToolType.eraser),
        ArtTool.create(name: 'Color Picker', type: ToolType.colorPicker),
      ],
      metadata: ArtMetadata(
        style: ArtStyle.digital,
        medium: ArtMedium.painting,
        theme: 'abstract',
      ),
    );
  }
  
  @override
  Future<void> initializeProject(WorkstationProject project) async {
    final artStructure = project.structure as ArtStructure;
    
    // Initialize canvas and rendering engine
    final renderer = CanvasRenderer(artStructure.canvas);
    await renderer.initialize();
    
    // Set up brand tracking for art metrics
    await _setupArtBrandTracking(project);
  }
  
  Future<void> _setupArtBrandTracking(WorkstationProject project) async {
    final brandTracker = ArtBrandTracker();
    
    // Track artistic metrics
    brandTracker.trackMetric('canvas_size', project.artMetadata.canvasSize);
    brandTracker.trackMetric('layer_count', project.artStructure.layers.length);
    brandTracker.trackMetric('art_style', project.artMetadata.style.toString());
    
    // Track brand-related metrics
    brandTracker.trackMetric('gallery_submissions', 0);
    brandTracker.trackMetric('commission_count', 0);
    brandTracker.trackMetric('social_shares', 0);
  }
}
```

## Privacy Control System

```dart
// lib/infrastructure/privacy/privacy_controller.dart
class PrivacyController {
  final TrustActionRepository _trustActionRepository;
  final EncryptionService _encryption;
  final AuditLogger _auditLogger;
  
  Future<bool> canShareData(String domicileId, DataType dataType, String targetContext) async {
    // Check if there's an existing trust action for this data sharing
    final existingActions = await _trustActionRepository.getActiveActions(
      domicileId, 
      dataType, 
      targetContext,
    );
    
    return existingActions.isNotEmpty && existingActions.any((action) => action.isValid());
  }
  
  Future<TrustAction> createTrustAction(String domicileId, TrustActionRequest request) async {
    // Validate request
    await _validateTrustActionRequest(request);
    
    // Create cryptographic proof of user consent
    final consentProof = await _createConsentProof(request);
    
    final trustAction = TrustAction.create(
      domicileId: domicileId,
      dataType: request.dataType,
      targetContext: request.targetContext,
      conditions: request.conditions,
      consentProof: consentProof,
      expiresAt: DateTime.now().add(Duration(days: request.validityDays)),
      createdAt: DateTime.now(),
    );
    
    await _trustActionRepository.save(trustAction);
    
    // Log for audit trail
    await _auditLogger.logTrustAction(trustAction);
    
    return trustAction;
  }
  
  Future<bool> executeTrustAction(String actionId, UserSignature signature) async {
    final trustAction = await _trustActionRepository.findById(actionId);
    if (trustAction == null || !trustAction.isValid()) {
      return false;
    }
    
    // Verify user signature
    final isValidSignature = await _verifyUserSignature(
      trustAction.consentProof,
      signature,
    );
    
    if (!isValidSignature) {
      await _auditLogger.logFailedSignature(actionId, signature);
      return false;
    }
    
    // Execute the data sharing operation
    try {
      await _executeDataSharing(trustAction);
      
      // Mark action as executed
      trustAction.executedAt = DateTime.now();
      await _trustActionRepository.save(trustAction);
      
      return true;
    } catch (e) {
      await _auditLogger.logExecutionFailure(actionId, e);
      return false;
    }
  }
  
  Future<List<FeedItem>> filterFeedItems(List<FeedItem> items, String domicileId) async {
    final filteredItems = <FeedItem>[];
    
    for (final item in items) {
      // Check if user has consent to view this type of content
      final canView = await canShareData(
        domicileId,
        DataType.feedContent,
        'domicile_feed',
      );
      
      if (canView) {
        // Sanitize item based on privacy settings
        final sanitizedItem = await _sanitizeFeedItem(item, domicileId);
        filteredItems.add(sanitizedItem);
      }
    }
    
    return filteredItems;
  }
  
  Future<FeedItem> _sanitizeFeedItem(FeedItem item, String domicileId) async {
    // Remove potentially sensitive metadata
    final sanitizedMetadata = <String, dynamic>{};
    
    for (final entry in item.metadata.entries) {
      if (_isSafeMetadata(entry.key, entry.value)) {
        sanitizedMetadata[entry.key] = entry.value;
      }
    }
    
    return item.copyWith(metadata: sanitizedMetadata);
  }
  
  bool _isSafeMetadata(String key, dynamic value) {
    // Define safe metadata keys
    final safeKeys = {
      'title', 'content', 'author', 'publishedAt', 'source', 'url',
      'imageUrl', 'retweets', 'likes', 'shares',
    };
    
    return safeKeys.contains(key) && !_isSensitiveData(value);
  }
  
  bool _isSensitiveData(dynamic value) {
    // Check for potentially sensitive data patterns
    if (value is String) {
      final patterns = [
        RegExp(r'\b\d{4}[-]?\d{4}[-]?\d{4}[-]?\d{4}\b'), // Credit card numbers
        RegExp(r'\b\d{3}-\d{2}-\d{4}\b'), // SSN patterns
        RegExp(r'\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b'), // Email addresses
      ];
      
      return patterns.any((pattern) => pattern.hasMatch(value));
    }
    
    return false;
  }
  
  Future<ConsentProof> _createConsentProof(TrustActionRequest request) async {
    final consentData = {
      'domicile_id': request.domicileId,
      'data_type': request.dataType.toString(),
      'target_context': request.targetContext,
      'conditions': request.conditions,
      'timestamp': DateTime.now().toIso8601String(),
      'nonce': _generateCryptographicNonce(),
    };
    
    final signature = await _encryption.signData(consentData, request.userPrivateKey);
    
    return ConsentProof(
      data: consentData,
      signature: signature,
      publicKey: request.userPublicKey,
    );
  }
}
```

## 3D Domicile Interface

```dart
// lib/infrastructure/3d/domicile_visualizer.dart
class DomicileVisualizer {
  late ThreeJSRenderer _renderer;
  late Scene _scene;
  late Camera _camera;
  final DomicileService _domicileService;
  final User _currentUser;
  
  Map<String, Mesh> _spaceMeshes = {};
  Map<String, Mesh> _feedVisualization = {};
  
  DomicileVisualizer(this._domicileService, this._currentUser);
  
  void initialize(CanvasElement canvas) {
    _renderer = ThreeJSRenderer(canvas: canvas);
    _scene = Scene();
    _camera = PerspectiveCamera(75, canvas.width / canvas.height, 0.1, 1000);
    
    _setupPrivateEnvironment();
    _setupLighting();
  }
  
  Future<void> renderDomicile(String domicileId) async {
    final domicile = await _domicileService.getDomicile(domicileId);
    
    await _createPrivateSpaces(domicile);
    await _visualizeFeeds(domicile);
    await _createWorkstationArea(domicile);
    
    _renderer.render(_scene, _camera);
  }
  
  Future<void> _createPrivateSpaces(Domicile domicile) async {
    // Create private, enclosed spaces for different functions
    
    // Feed aggregation space
    final feedSpace = _createEnclosedSpace(
      position: Vector3(-50, 0, 0),
      size: Vector3(40, 30, 40),
      material: _getPrivacyMaterial(0.8), // Semi-transparent for visibility
      label: 'Feed Aggregation',
    );
    _spaceMeshes['feeds'] = feedSpace;
    _scene.add(feedSpace);
    
    // Handshake gateway space
    final gatewaySpace = _createEnclosedSpace(
      position: Vector3(0, 0, 0),
      size: Vector3(30, 25, 30),
      material: _getPrivacyMaterial(0.9), // More opaque
      label: 'Village Gateway',
    );
    _spaceMeshes['gateway'] = gatewaySpace;
    _scene.add(gatewaySpace);
    
    // Workstation space
    final workstationSpace = _createEnclosedSpace(
      position: Vector3(50, 0, 0),
      size: Vector3(50, 35, 50),
      material: _getPrivacyMaterial(0.7), // Less opaque for creative work
      label: 'Workstation',
    );
    _spaceMeshes['workstation'] = workstationSpace;
    _scene.add(workstationSpace);
  }
  
  Future<void> _visualizeFeeds(Domicile domicile) async {
    final feedItems = await _domicileService.getAggregatedFeeds(domicile.id);
    
    // Create feed visualization as floating particles
    for (int i = 0; i < feedItems.length && i < 50; i++) {
      final item = feedItems[i];
      final particle = _createFeedParticle(item, i);
      _feedVisualization[item.id] = particle;
      _scene.add(particle);
    }
  }
  
  Mesh _createFeedParticle(FeedItem item, int index) {
    final geometry = SphereGeometry(radius: 2.0);
    final material = MeshBasicMaterial(
      color: _getFeedTypeColor(item.type),
      opacity: 0.8,
      transparent: true,
    );
    
    final particle = Mesh(geometry, material);
    
    // Position particles in a spiral pattern within feed space
    final angle = index * 0.5;
    final radius = 15.0 + (index % 10) * 2.0;
    final height = -10.0 + (index % 5) * 5.0;
    
    particle.position.setValues(
      -50 + radius * cos(angle),
      height,
      radius * sin(angle),
    );
    
    // Add hover interaction to show feed details
    _addFeedInteraction(particle, item);
    
    return particle;
  }
  
  Mesh _createEnclosedSpace({
    required Vector3 position,
    required Vector3 size,
    required Material material,
    required String label,
  }) {
    // Create room with walls, floor, and ceiling
    final group = Group();
    
    // Floor
    final floorGeometry = PlaneGeometry(width: size.x, height: size.z);
    final floor = Mesh(floorGeometry, material);
    floor.rotation.x = -pi / 2;
    floor.position.y = -size.y / 2;
    group.add(floor);
    
    // Ceiling
    final ceiling = Mesh(floorGeometry, material);
    ceiling.rotation.x = pi / 2;
    ceiling.position.y = size.y / 2;
    group.add(ceiling);
    
    // Walls
    final wallGeometry = PlaneGeometry(width: size.x, height: size.y);
    
    // Back wall
    final backWall = Mesh(wallGeometry, material);
    backWall.position.z = -size.z / 2;
    group.add(backWall);
    
    // Front wall (with doorway)
    final frontWall = Mesh(wallGeometry, material);
    frontWall.position.z = size.z / 2;
    group.add(frontWall);
    
    // Side walls
    final sideWallGeometry = PlaneGeometry(width: size.z, height: size.y);
    
    final leftWall = Mesh(sideWallGeometry, material);
    leftWall.rotation.y = pi / 2;
    leftWall.position.x = -size.x / 2;
    group.add(leftWall);
    
    final rightWall = Mesh(sideWallGeometry, material);
    rightWall.rotation.y = -pi / 2;
    rightWall.position.x = size.x / 2;
    group.add(rightWall);
    
    group.position.setFrom(position);
    
    return group;
  }
  
  Material _getPrivacyMaterial(double opacity) {
    return MeshPhongMaterial(
      color: Color(0x2C3E50), // Dark blue-gray
      transparent: true,
      opacity: opacity,
      side: DoubleSide,
    );
  }
  
  Color _getFeedTypeColor(FeedType type) {
    switch (type) {
      case FeedType.twitter:
        return Color(0x1DA1F2); // Twitter blue
      case FeedType.news:
        return Color(0xFF6B6B); // News gray
      case FeedType.instagram:
        return Color(0xE4405F); // Instagram pink
      case FeedType.youtube:
        return Color(0xFF0000); // YouTube red
      default:
        return Color(0x888888); // Default gray
    }
  }
  
  void _setupPrivateEnvironment() {
    // Create skybox that emphasizes privacy
    final skyboxTexture = _createPrivacySkybox();
    _scene.background = skyboxTexture;
    
    // Add subtle, calming lighting
    final ambientLight = AmbientLight(0x404040, 0.3);
    _scene.add(ambientLight);
    
    final pointLight = PointLight(0x6B46C1, 0.5, 200); // Purple light
    pointLight.position.setValues(0, 50, 0);
    _scene.add(pointLight);
  }
  
  Texture _createPrivacySkybox() {
    // Create a gradient skybox that suggests privacy and security
    // This would be a texture with dark blues and purples
    return TextureLoader.load('assets/textures/privacy_skybox.jpg');
  }
}
```

## Supporting Models

```dart
// lib/domain/models/domicile.dart
class Domicile {
  String id;
  User owner;
  String publicKey;
  String encryptedSymmetricKey;
  DateTime createdAt;
  DateTime? lastAccessedAt;
  List<FeedSource> feedSources;
  List<VillageHandshake> activeHandshakes;
  List<WorkstationProject> projects;
  
  Domicile({
    required this.id,
    required this.owner,
    required this.publicKey,
    required this.encryptedSymmetricKey,
    required this.createdAt,
    this.lastAccessedAt,
    this.feedSources = const [],
    this.activeHandshakes = const [],
    this.projects = const [],
  });
  
  factory Domicile.create({
    required User owner,
    required String publicKey,
    required String encryptedSymmetricKey,
  }) {
    return Domicile(
      id: _generateId(),
      owner: owner,
      publicKey: publicKey,
      encryptedSymmetricKey: encryptedSymmetricKey,
      createdAt: DateTime.now(),
    );
  }
}

enum ProjectType { music, art, video, writing }

class TrustAction {
  String id;
  String domicileId;
  DataType dataType;
  String targetContext;
  Map<String, dynamic> conditions;
  ConsentProof consentProof;
  DateTime expiresAt;
  DateTime createdAt;
  DateTime? executedAt;
  
  bool isValid() => DateTime.now().isBefore(expiresAt) && executedAt == null;
}

enum DataType { feedContent, projectData, personalInfo, collaborationHistory }

enum HandshakeStatus { pending, established, terminated, failed }
```

This Domicile architecture provides a comprehensive private tenant space with zero-knowledge privacy principles, encrypted data storage, aggregated feeds, multi-village access, and creative workstation tools while maintaining strict privacy controls and audit trails.
