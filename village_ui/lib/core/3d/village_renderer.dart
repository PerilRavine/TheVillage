import 'dart:math';
import 'dart:html';
import 'dart:typed_data';
import 'package:vector_math/vector_math.dart';
import 'scene_graph.dart';

/// WebGL-based 3D renderer for The Village
/// Uses HTML5 Canvas for browser compatibility
class VillageRenderer {
  final SceneGraph _sceneGraph;
  CanvasElement? _canvas;
  CanvasRenderingContext2D? _context;
  final Map<String, String> _shaders = {};
  final Map<String, dynamic> _textures = {};
  
  // Performance metrics
  int _frameCount = 0;
  double _frameTime = 0.0;
  double _totalFrameTime = 0.0;
  
  VillageRenderer(this._sceneGraph) {
    // Initialize after DOM is ready
    _canvas = querySelector('#village-canvas') as CanvasElement?;
    if (_canvas != null) {
      _context = _canvas!.context2D;
      _initializeShaders();
      _initializeTextures();
      _setupLighting();
    }
  }
  
  /// Render the entire scene
  void render(double deltaTime) {
    final stopwatch = Stopwatch()..start();
    
    // Clear canvas
    _context!.clearRect(0, 0, _canvas!.width!, _canvas!.height!);
    
    /// Get visible nodes for culling
    final visibleNodes = _sceneGraph.nodes.where((node) => node.visible).toList();
    
    // Render each node
    for (final node in visibleNodes) {
      _renderNode(node);
    }
    
    // Update performance metrics
    _updatePerformanceMetrics(stopwatch.elapsedMicroseconds() / 1000000.0);
  }
  
  /// Render individual node
  void _renderNode(SceneNode node) {
    if (_context == null) return;
    
    final position = node.position;
    final size = node.size;
    final color = node.color;
    
    // Draw node as circle
    _context!
      ..fillStyle = 'rgba(${(color.r * 255).toInt()}, ${(color.g * 255).toInt()}, ${(color.b * 255).toInt()}, 0.8)'
      ..beginPath()
      ..arc(position.x, position.y, size / 2, 0, 2 * math.pi)
      ..fill()
      ..strokeStyle = 'rgba(255, 255, 255, 0.5)'
      ..lineWidth = 2
      ..stroke();
    
    // Draw node label
    _context
      ..fillStyle = 'white'
      ..font = '12px Arial'
      ..textAlign = 'center'
      ..fillText(node.name, position.x, position.y - size / 2 - 10);
  }
  
  /// Render a batch of nodes with same material
  void _renderMaterialBatch(String material, List<SceneNode> nodes) {
    // Simple batch rendering for 2D canvas
    for (final node in nodes) {
      _renderNode(node);
    }
  }
  
  /// Create instance data for a node (simplified)
  List<double> _createInstanceData(SceneNode node) {
    return [
      node.position.x, node.position.y, node.position.z,
      node.scale.x, node.scale.y, node.scale.z,
      node.rotation.y
    ];
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
    
    _shaders[material.id] = shader;
    return shader;
  }
  
  /// Get or create texture for material
  dynamic _getTextureForMaterial(Material material) {
    if (_textures.containsKey(material.id)) {
      return _textures[material.id]!;
    }
    
    final texture = 'basic_texture';
    
    _textures[material.id] = texture;
    return texture;
  }
  
  /// Initialize all shaders
  void _initializeShaders() {
    // Create basic vertex and fragment shaders
    _shaders['default'] = 'basic_2d_shader';
    _shaders['default_fragment'] = 'basic_2d_fragment';
  }
  
  /// Initialize all textures
  void _initializeTextures() {
    // Create default texture
    _textures['default'] = 'basic_texture';
  }
  
  /// Setup lighting
  void _setupLighting() {
    // Basic lighting setup
    print('Lighting initialized');
  }
  
  /// Update performance metrics
  void _updatePerformanceMetrics(double frameTime) {
    _frameCount++;
    _frameTime = frameTime;
    _totalFrameTime += frameTime;
    
    if (_frameCount % 60 == 0) {
      final avgFrameTime = _totalFrameTime / _frameCount;
      print('Performance: ${avgFrameTime.toStringAsFixed(2)}ms avg, ${_frameTime.toStringAsFixed(2)}ms current');
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
