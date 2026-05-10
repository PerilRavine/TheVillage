import 'dart:html';
import 'dart:js_interop';
import 'dart:js';

/// 3D Village Map with WebGL rendering
/// Fallback implementation when Flutter/Wasm is not available
class VillageMap3D {
  final CanvasElement _canvas;
  final WebGLRenderingContext _gl;
  final List<VillageNode> _nodes = [];
  final Map<String, VillageEdge> _edges = {};
  
  // Camera properties
  double _cameraX = 0.0;
  double _cameraY = 10.0;
  double _cameraZ = 20.0;
  double _cameraRotation = 0.0;
  
  VillageMap3D() : _canvas = querySelector('#village-canvas') as CanvasElement,
       _gl = _canvas.getContext('webgl2') as WebGLRenderingContext {
    _initializeWebGL();
    _initializeNodes();
    _startAnimation();
  }
  
  /// Initialize WebGL context
  void _initializeWebGL() {
    if (_gl == null) {
      throw Exception('WebGL2 not supported');
    }
    
    _gl.enable(_gl.DEPTH_TEST);
    _gl.enable(_gl.CULL_FACE);
    _gl.cullFace(_gl.BACK);
    
    // Set up shaders
    _setupShaders();
    
    // Set up lighting
    _setupLighting();
  }
  
  /// Set up shaders
  void _setupShaders() {
    // Vertex shader
    final vertexShaderSource = '''
      attribute vec3 aPosition;
      attribute vec3 aNormal;
      uniform mat4 uProjectionMatrix;
      uniform mat4 uViewMatrix;
      uniform mat4 uModelMatrix;
      
      varying vec3 vNormal;
      varying vec3 vPosition;
      
      void main() {
        vPosition = (uModelMatrix * vec4(aPosition, 1.0)).xyz;
        vNormal = mat3(uModelMatrix) * aNormal;
        gl_Position = uProjectionMatrix * uViewMatrix * vec4(vPosition, 1.0);
      }
    ''';
    
    // Fragment shader
    final fragmentShaderSource = '''
      precision mediump float;
      varying vec3 vNormal;
      varying vec3 vPosition;
      
      uniform vec3 uLightPosition;
      uniform vec3 uLightColor;
      uniform vec4 uMaterialColor;
      
      void main() {
        vec3 normal = normalize(vNormal);
        vec3 lightDir = normalize(uLightPosition - vPosition);
        float diff = max(dot(normal, lightDir), 0.0);
        
        vec3 ambient = vec3(0.1, 0.1, 0.1);
        vec3 diffuse = diff * uLightColor;
        
        gl_FragColor = vec4(ambient + diffuse * uMaterialColor.rgb, uMaterialColor.a);
      }
    ''';
    
    // Compile shaders
    final vertexShader = _gl.createShader(_gl.VERTEX_SHADER);
    _gl.shaderSource(vertexShader, vertexShaderSource);
    _gl.compileShader(vertexShader);
    
    final fragmentShader = _gl.createShader(_gl.FRAGMENT_SHADER);
    _gl.shaderSource(fragmentShader, fragmentShaderSource);
    _gl.compileShader(fragmentShader);
    
    // Create shader program
    final shaderProgram = _gl.createProgram();
    _gl.attachShader(shaderProgram, vertexShader);
    _gl.attachShader(shaderProgram, fragmentShader);
    _gl.linkProgram(shaderProgram);
    _gl.useProgram(shaderProgram);
  }
  
  /// Set up lighting
  void _setupLighting() {
    final lightPositionLocation = _gl.getUniformLocation(_gl.getParameter(_gl.CURRENT_PROGRAM), 'uLightPosition');
    final lightColorLocation = _gl.getUniformLocation(_gl.getParameter(_gl.CURRENT_PROGRAM), 'uLightColor');
    final materialColorLocation = _gl.getUniformLocation(_gl.getParameter(_gl.CURRENT_PROGRAM), 'uMaterialColor');
    
    _gl.uniform3f(lightPositionLocation, -1.0, -1.0, -1.0);
    _gl.uniform3f(lightColorLocation, 0.8, 0.8, 0.8);
    _gl.uniform4f(materialColorLocation, 1.0, 1.0, 1.0, 1.0);
  }
  
  /// Initialize test nodes
  void _initializeNodes() {
    // Add test villages
    _nodes.add(VillageNode(
      id: 'village1',
      name: 'Tech Village',
      position: Vector3(100.0, 0.0, 100.0),
      color: Vector3(0.2, 0.6, 1.0),
      size: 20.0
    ));
    
    _nodes.add(VillageNode(
      id: 'village2',
      name: 'Art Village',
      position: Vector3(-100.0, 0.0, 100.0),
      color: Vector3(1.0, 0.2, 0.6),
      size: 20.0
    ));
    
    _nodes.add(VillageNode(
      id: 'village3',
      name: 'Science Village',
      position: Vector3(0.0, 0.0, -150.0),
      color: Vector3(0.6, 0.2, 1.0),
      size: 20.0
    ));
    
    // Add test users
    _nodes.add(VillageNode(
      id: 'user1',
      name: 'Alice',
      position: Vector3(120.0, 0.0, 80.0),
      color: Vector3(0.8, 0.4, 0.8),
      size: 10.0
    ));
    
    _nodes.add(VillageNode(
      id: 'user2',
      name: 'Bob',
      position: Vector3(-80.0, 0.0, 120.0),
      color: Vector3(0.4, 0.8, 0.8),
      size: 10.0
    ));
  }
  
  /// Start animation loop
  void _startAnimation() {
    void animate(num timestamp) {
      _updateCamera();
      _render();
      
      requestAnimationFrame(animate);
    }
    
    requestAnimationFrame(animate);
  }
  
  /// Update camera
  void _updateCamera() {
    // Simple camera rotation
    _cameraRotation += 0.005;
  }
  
  /// Render the scene
  void _render() {
    _gl.clearColor(0.2, 0.3, 0.5, 1.0);
    _gl.clear(_gl.COLOR_BUFFER_BIT | _gl.DEPTH_BUFFER_BIT);
    
    // Get shader locations
    final positionLocation = _gl.getAttribLocation(_gl.getParameter(_gl.CURRENT_PROGRAM), 'aPosition');
    final normalLocation = _gl.getAttribLocation(_gl.getParameter(_gl.CURRENT_PROGRAM), 'aNormal');
    final projectionMatrixLocation = _gl.getUniformLocation(_gl.getParameter(_gl.CURRENT_PROGRAM), 'uProjectionMatrix');
    final viewMatrixLocation = _gl.getUniformLocation(_gl.getParameter(_gl.CURRENT_PROGRAM), 'uViewMatrix');
    final modelMatrixLocation = _gl.getUniformLocation(_gl.getParameter(_gl.CURRENT_PROGRAM), 'uModelMatrix');
    
    // Set up matrices
    final projectionMatrix = _makePerspectiveMatrix();
    final viewMatrix = _makeViewMatrix();
    
    _gl.uniformMatrix4fv(projectionMatrixLocation, projectionMatrix.storage);
    _gl.uniformMatrix4fv(viewMatrixLocation, viewMatrix.storage);
    _gl.uniformMatrix4fv(modelMatrixLocation, Matrix4.identity().storage);
    
    // Render nodes
    for (final node in _nodes) {
      _renderNode(node, positionLocation, normalLocation, modelMatrixLocation);
    }
  }
  
  /// Render a single node
  void _renderNode(VillageNode node, int positionLocation, int normalLocation, int modelMatrixLocation) {
    final modelMatrix = Matrix4.translation(node.position);
    _gl.uniformMatrix4fv(modelMatrixLocation, modelMatrix.storage);
    
    // Create vertices for a cube
    final vertices = _createCubeVertices(node.position, node.size);
    final vertexBuffer = _gl.createBuffer();
    _gl.bindBuffer(_gl.ARRAY_BUFFER, vertexBuffer);
    _gl.bufferData(_gl.ARRAY_BUFFER, new Float32List.fromList(vertices));
    
    // Set up attributes
    _gl.vertexAttribPointer(positionLocation, 3, _gl.FLOAT, false, 6 * 4, 0);
    _gl.enableVertexAttribArray(positionLocation);
    
    _gl.vertexAttribPointer(normalLocation, 3, _gl.FLOAT, false, 6 * 4, 3 * 4);
    _gl.enableVertexAttribArray(normalLocation);
    
    // Draw the cube
    _gl.drawArrays(_gl.TRIANGLES, 0, 36);
  }
  
  /// Create vertices for a cube
  List<double> _createCubeVertices(Vector3 position, double size) {
    final s = size / 2.0;
    final vertices = [
      // Front face
      position.x - s, position.y - s, position.z + s,
      position.x + s, position.y - s, position.z + s,
      position.x + s, position.y + s, position.z + s,
      position.x - s, position.y + s, position.z + s,
      position.x - s, position.y - s, position.z + s,
      
      // Back face
      position.x - s, position.y - s, position.z - s,
      position.x + s, position.y - s, position.z - s,
      position.x + s, position.y + s, position.z - s,
      position.x + s, position.y + s, position.z - s,
      position.x - s, position.y + s, position.z - s,
      
      // Top face
      position.x - s, position.y + s, position.z - s,
      position.x + s, position.y + s, position.z - s,
      position.x + s, position.y + s, position.z - s,
      position.x + s, position.y + s, position.z - s,
      position.x - s, position.y + s, position.z - s,
      
      // Bottom face
      position.x - s, position.y - s, position.z + s,
      position.x + s, position.y - s, position.z + s,
      position.x + s, position.y - s, position.z + s,
      position.x + s, position.y + s, position.z + s,
      position.x - s, position.y - s, position.z + s,
    ];
    
    return vertices;
  }
  
  /// Create perspective projection matrix
  List<double> _makePerspectiveMatrix() {
    final fov = 45.0 * (pi / 180.0);
    final aspect = _canvas.width! / _canvas.height!;
    final near = 0.1;
    final far = 1000.0;
    
    final f = 1.0 / (tan(fov / 2.0));
    final rangeInv = 1.0 / (far - near);
    
    return [
      f / aspect, 0, 0,
      0, f, 0, 0,
      0, 0, (far + near) * rangeInv, -1,
      0, 0, 2 * far * near * rangeInv, -1,
      0, 1, 0, 0,
      1, 0, 0, 0
    ];
  }
  
  /// Create view matrix
  List<double> _makeViewMatrix() {
    final eye = Vector3(_cameraX, _cameraY, _cameraZ);
    
    // Simple rotation around Y axis
    final cosAngle = cos(_cameraRotation);
    final sinAngle = sin(_cameraRotation);
    
    final x = eye.x * cosAngle - eye.z * sinAngle;
    final z = eye.x * sinAngle + eye.z * cosAngle;
    
    return [
      cosAngle, 0, sinAngle, 0,
      0, 1, 0, 0,
      -sinAngle, 0, cosAngle, 0,
      0, 0, 0, 0,
      -(x * cosAngle - eye.y * sinAngle),
      -(x * sinAngle + eye.y * cosAngle),
      -(eye.z),
      1, 0, 0, 0
    ];
  }
}

/// Simple village node for WebGL rendering
class VillageNode {
  final String id;
  final String name;
  final Vector3 position;
  final Vector3 color;
  final double size;
  
  VillageNode({
    required this.id,
    required this.name,
    required this.position,
    required this.color,
    required this.size,
  });
}

/// Simple 3D vector class
class Vector3 {
  final double x, y, z;
  
  const Vector3(this.x, this.y, this.z);
  
  double get length => sqrt(x * x + y * y + z * z);
  
  Vector3 operator +(Vector3 other) => Vector3(x + other.x, y + other.y, z + other.z);
  Vector3 operator -(Vector3 other) => Vector3(x - other.x, y - other.y, z - other.z);
  Vector3 operator *(double scalar) => Vector3(x * scalar, y * scalar, z * scalar);
}

/// Matrix4 class for 3D transformations
class Matrix4 {
  final List<double> storage;
  
  const Matrix4(this.storage);
  
  static Matrix4 identity() => Matrix4([
    1, 0, 0, 0,
    0, 1, 0, 0,
    0, 0, 1, 0,
    0, 0, 0, 1,
    0, 0, 0, 0,
    0, 0, 0, 1
  ]);
  
  static Matrix4 translation(Vector3 translation) => Matrix4([
    1, 0, 0, 0,
    0, 1, 0, 0,
    0, 0, 1, 0,
    translation.x, translation.y, translation.z, 1,
    0, 0, 0, 0,
    0, 0, 0, 1
  ]);
}

// Math constants
const double pi = 3.141592653589793;
double sqrt(double x) => x.sqrt();
double cos(double x) => x.cos();
double sin(double x) => x.sin();
double tan(double x) => x.tan();
