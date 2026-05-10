import 'dart:math';
import 'dart:wasm';
import 'package:vector_math/vector_math.dart';
import 'scene_graph.dart';

/// WasmGC-optimized 3D renderer for The Village
/// Uses Skwasm for maximum performance
class VillageRenderer {
  final SceneGraph _sceneGraph;
  final SkwasmContext _context;
  final Map<String, SkwasmShader> _shaders = {};
  final Map<String, SkwasmTexture> _textures = {};
  
  // Performance metrics
  int _frameCount = 0;
  double _frameTime = 0.0;
  double _totalFrameTime = 0.0;
  
  VillageRenderer(this._sceneGraph) : _context = SkwasmContext.create() {
    _initializeShaders();
    _initializeTextures();
    _setupLighting();
  }
  
  /// Render the entire scene
  void render(double deltaTime) {
    final stopwatch = Stopwatch()..start();
    
    // Update scene
    _sceneGraph.update(deltaTime);
    
    // Begin frame
    _context.beginFrame();
    
    // Get visible nodes for culling
    final visibleNodes = _sceneGraph.getVisibleNodes();
    
    // Batch render by material type
    final nodesByMaterial = <Material, List<SceneNode>>{};
    for (final node in visibleNodes) {
      if (!nodesByMaterial.containsKey(node.material)) {
        nodesByMaterial[node.material] = [];
      }
      nodesByMaterial[node.material]!.add(node);
    }
    
    // Render each material batch
    for (final entry in nodesByMaterial.entries) {
      _renderMaterialBatch(entry.key, entry.value);
    }
    
    // End frame
    _context.endFrame();
    
    // Update performance metrics
    _updatePerformanceMetrics(stopwatch.elapsedMicroseconds() / 1000000.0);
  }
  
  /// Render a batch of nodes with the same material
  void _renderMaterialBatch(Material material, List<SceneNode> nodes) {
    final shader = _getShaderForMaterial(material);
    final texture = _getTextureForMaterial(material);
    
    _context.bindShader(shader);
    _context.bindTexture(texture);
    
    // Create instanced rendering data
    final instanceData = <Float32List>[];
    for (final node in nodes) {
      instanceData.addAll(_createInstanceData(node));
    }
    
    // Render instances
    _context.renderInstanced(
      primitiveType: SkwasmPrimitiveType.triangles,
      instanceCount: nodes.length,
      instanceData: instanceData,
    );
  }
  
  /// Create instance data for a node
  List<double> _createInstanceData(SceneNode node) {
    final matrix = Matrix4.identity()
      ..translate(node.position)
      ..rotateY(node.rotation.y)
      ..scale(node.scale);
    
    return matrix.storage;
  }
  
  /// Get or create shader for material
  SkwasmShader _getShaderForMaterial(Material material) {
    if (_shaders.containsKey(material.id)) {
      return _shaders[material.id]!;
    }
    
    final shader = _context.createShader('''
      uniform mat4 uProjectionMatrix;
      uniform mat4 uViewMatrix;
      uniform mat4 uModelMatrix;
      uniform vec3 uLightPosition;
      uniform vec3 uLightColor;
      uniform vec4 uMaterialColor;
      uniform float uMetallic;
      uniform float uRoughness;
      
      in vec3 aPosition;
      in vec3 aNormal;
      in vec2 aTexCoord;
      
      out vec4 fragColor;
      
      void main() {
        vec3 worldPos = (uModelMatrix * vec4(aPosition, 1.0)).xyz;
        vec3 normal = normalize(mat3(uModelMatrix) * aNormal);
        
        // Lighting calculation
        vec3 lightDir = normalize(uLightPosition - worldPos);
        float diff = max(dot(normal, lightDir), 0.0);
        vec3 diffuse = diff * uLightColor;
        
        // Material properties
        vec3 viewDir = normalize(-worldPos);
        vec3 reflectDir = reflect(-lightDir, normal);
        float spec = pow(max(dot(viewDir, reflectDir), 0.0), uRoughness * 32.0);
        
        // Combine lighting
        vec3 ambient = vec3(0.1, 0.1, 0.1);
        vec3 baseColor = uMaterialColor.rgb;
        vec3 finalColor = ambient + diffuse * baseColor + spec * uMetallic;
        
        fragColor = vec4(finalColor, uMaterialColor.a);
      }
    ''');
    
    _shaders[material.id] = shader;
    return shader;
  }
  
  /// Get or create texture for material
  SkwasmTexture _getTextureForMaterial(Material material) {
    if (_textures.containsKey(material.id)) {
      return _textures[material.id]!;
    }
    
    final texture = _context.createTexture(
      width: 1,
      height: 1,
      format: SkwasmTextureFormat.rgb8,
    );
    
    // Set texture data from material color
    final pixelData = <int>[
      (material.color.red * 255).round(),
      (material.color.green * 255).round(),
      (material.color.blue * 255).round(),
      255,
    ];
    
    texture.setPixelData(pixelData);
    _textures[material.id] = texture;
    return texture;
  }
  
  /// Initialize shaders
  void _initializeShaders() {
    // Create reputation shader for user avatars
    _shaders['reputation'] = _context.createShader('''
      uniform mat4 uProjectionMatrix;
      uniform mat4 uViewMatrix;
      uniform mat4 uModelMatrix;
      uniform vec3 uReputationColor;
      uniform float uReputationIntensity;
      uniform float uTime;
      
      in vec3 aPosition;
      in vec3 aNormal;
      
      out vec4 fragColor;
      
      void main() {
        // Pulsing effect based on reputation
        float pulse = sin(uTime * 2.0) * 0.5 + 0.5;
        float intensity = uReputationIntensity * pulse;
        
        // Mix reputation color with white based on intensity
        vec3 color = mix(uReputationColor, vec3(1.0, 1.0, 1.0), intensity * 0.3);
        
        fragColor = vec4(color, 1.0);
      }
    ''');
    
    // Create village shader for spaces
    _shaders['village'] = _context.createShader('''
      uniform mat4 uProjectionMatrix;
      uniform mat4 uViewMatrix;
      uniform mat4 uModelMatrix;
      uniform vec3 uVillageColor;
      uniform float uPopulation;
      uniform float uTime;
      
      in vec3 aPosition;
      in vec3 aNormal;
      in vec2 aTexCoord;
      
      out vec4 fragColor;
      
      void main() {
        // Glow effect for active villages
        float glow = sin(uTime * 1.5) * 0.5 + 0.5;
        float intensity = min(uPopulation / 50.0, 1.0) * glow;
        
        vec3 color = uVillageColor + vec3(intensity * 0.2);
        
        fragColor = vec4(color, 1.0);
      }
    ''');
  }
  
  /// Initialize textures
  void _initializeTextures() {
    // Create default textures
    _textures['default'] = _context.createTexture(
      width: 1,
      height: 1,
      format: SkwasmTextureFormat.rgb8,
    );
  }
  
  /// Setup lighting
  void _setupLighting() {
    _context.setAmbientLight(Vector3(0.2, 0.2, 0.2));
    _context.setDirectionalLight(
      direction: Vector3(-1.0, -1.0, -1.0),
      color: Vector3(0.8, 0.8, 0.8),
    );
  }
  
  /// Update performance metrics
  void _updatePerformanceMetrics(double frameTime) {
    _frameCount++;
    _frameTime = frameTime;
    _totalFrameTime += frameTime;
    
    // Log average FPS every 60 frames
    if (_frameCount % 60 == 0) {
      final avgFrameTime = _totalFrameTime / 60.0;
      final avgFPS = 1.0 / avgFrameTime;
      
      print('Average FPS: ${avgFPS.toStringAsFixed(2)}');
      print('Frame time: ${avgFrameTime.toStringAsFixed(3)}ms');
      
      _totalFrameTime = 0.0;
    }
  }
  
  /// Get current FPS
  double getFPS() {
    return _frameTime > 0 ? 1.0 / _frameTime : 0.0;
  }
  
  /// Dispose resources
  void dispose() {
    for (final shader in _shaders.values) {
      shader.dispose();
    }
    for (final texture in _textures.values) {
      texture.dispose();
    }
    _context.dispose();
  }
}

/// Skwasm context wrapper
class SkwasmContext {
  static SkwasmContext create() {
    // Initialize Skwasm context
    return SkwasmContext._internal();
  }
  
  void beginFrame() {
    // Begin frame rendering
  }
  
  void endFrame() {
    // End frame rendering
  }
  
  SkwasmShader createShader(String source) {
    // Create shader from source
    return SkwasmShader._internal(source);
  }
  
  SkwasmTexture createTexture({
    required int width,
    required int height,
    required SkwasmTextureFormat format,
  }) {
    // Create texture
    return SkwasmTexture._internal(width, height, format);
  }
  
  void bindShader(SkwasmShader shader) {
    // Bind shader for rendering
  }
  
  void bindTexture(SkwasmTexture texture) {
    // Bind texture for rendering
  }
  
  void renderInstanced({
    required SkwasmPrimitiveType primitiveType,
    required int instanceCount,
    required Float32List instanceData,
  }) {
    // Render instanced geometry
  }
  
  void setAmbientLight(Vector3 color) {
    // Set ambient lighting
  }
  
  void setDirectionalLight({
    required Vector3 direction,
    required Vector3 color,
  }) {
    // Set directional lighting
  }
  
  void dispose() {
    // Dispose context resources
  }
}

/// Skwasm shader wrapper
class SkwasmShader {
  final String _source;
  
  SkwasmShader._internal(this._source);
  
  void dispose() {
    // Dispose shader resources
  }
}

/// Skwasm texture wrapper
class SkwasmTexture {
  final int width;
  final int height;
  final SkwasmTextureFormat format;
  
  SkwasmTexture._internal(this.width, this.height, this.format);
  
  void setPixelData(List<int> data) {
    // Set texture pixel data
  }
  
  void dispose() {
    // Dispose texture resources
  }
}

/// Skwasm primitive types
enum SkwasmPrimitiveType {
  triangles,
  lines,
  points,
}

/// Skwasm texture formats
enum SkwasmTextureFormat {
  rgb8,
  rgba8,
  float32,
}
