// Three.js Avatar Manager for VerzTec Chatbot
// Integrates Ready Player Me avatar with lipsync and ElevenLabs TTS

class AvatarManager {
  constructor(containerId, options = {}) {
    this.container = document.getElementById(containerId);
    this.options = {
      avatarUrl: options.avatarUrl || 'assets/avatars/models/64f1a714fe61576b46f27ca2.glb',
      animationsUrl: options.animationsUrl || 'assets/avatars/models/animations.glb',
      elevenlabsApiKey: options.elevenlabsApiKey || 'sk_72283c30a844b3d198dda76a38373741c8968217a9472ae7',
      voice: options.voice || 'pNInz6obpgDQGcFmaJgB', // Default Adam voice
      ...options
    };
    
    this.scene = null;
    this.camera = null;
    this.renderer = null;
    this.avatar = null;
    this.mixer = null;
    this.clock = new THREE.Clock();
    this.rhubarbLipsync = new RhubarbLipsync(); // Initialize Rhubarb lipsync
    this.audioElement = null;
    this.morphTargets = {};
    this.isInitialized = false;
    this.readyPlayerMeController = null;
    
    // Animation state
    this.idleAction = null;
    this.talkingAction = null;
    this.currentAnimation = null;
    
    this.init();
  }
  
  async init() {
    try {
      this.setupScene();
      this.setupLighting();
      this.setupCamera();
      this.setupRenderer();
      await this.loadAvatar();
      this.setupLipsync();
      this.setupControls();
      this.animate();
      
      this.isInitialized = true;
      console.log('Avatar Manager initialized successfully');
    } catch (error) {
      console.error('Failed to initialize Avatar Manager:', error);
    }
  }
  
  setupScene() {
    this.scene = new THREE.Scene();
    // Use a more neutral background that works well with the gradient container
    this.scene.background = null; // Transparent background to show container gradient
    
    // Add subtle environment
    const ambientLight = new THREE.AmbientLight(0x404040, 0.8);
    this.scene.add(ambientLight);
  }
  
  setupLighting() {
    // Key light
    const keyLight = new THREE.DirectionalLight(0xffffff, 1.2);
    keyLight.position.set(5, 5, 5);
    keyLight.castShadow = true;
    keyLight.shadow.mapSize.width = 2048;
    keyLight.shadow.mapSize.height = 2048;
    this.scene.add(keyLight);
    
    // Fill light
    const fillLight = new THREE.DirectionalLight(0xffffff, 0.8);
    fillLight.position.set(-3, 2, 4);
    this.scene.add(fillLight);
    
    // Rim light
    const rimLight = new THREE.DirectionalLight(0xffffff, 0.5);
    rimLight.position.set(0, 3, -5);
    this.scene.add(rimLight);
  }
  
  setupCamera() {
    this.camera = new THREE.PerspectiveCamera(
      50,
      this.container.clientWidth / this.container.clientHeight,
      0.1,
      1000
    );
    // Move camera closer to make avatar appear bigger
    this.camera.position.set(0, 1.6, 1.8);
    this.camera.lookAt(0, 1.5, 0);
  }
  
  setupRenderer() {
    this.renderer = new THREE.WebGLRenderer({ 
      antialias: true,
      alpha: true // Enable transparency
    });
    this.renderer.setSize(this.container.clientWidth, this.container.clientHeight);
    this.renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2)); // Limit for performance
    this.renderer.shadowMap.enabled = true;
    this.renderer.shadowMap.type = THREE.PCFSoftShadowMap;
    this.renderer.toneMapping = THREE.ACESFilmicToneMapping;
    this.renderer.toneMappingExposure = 1.2;
    this.renderer.setClearColor(0x000000, 0); // Transparent background
    
    this.container.appendChild(this.renderer.domElement);
    
    // Handle resize
    window.addEventListener('resize', () => this.onWindowResize());
  }
  
  async loadAvatar() {
    const loader = new THREE.GLTFLoader();
    
    try {
      // Load main avatar model
      const gltf = await new Promise((resolve, reject) => {
        loader.load(this.options.avatarUrl, resolve, 
          (progress) => {
            console.log('Avatar loading progress:', (progress.loaded / progress.total * 100) + '%');
          }, 
          reject);
      });
      
      this.avatar = gltf.scene;
      this.scene.add(this.avatar);
      
      // Load animations separately if available
      let animationClips = gltf.animations || [];
      
      if (this.options.animationsUrl && this.options.animationsUrl !== this.options.avatarUrl) {
        try {
          const animationGltf = await new Promise((resolve, reject) => {
            loader.load(this.options.animationsUrl, resolve, 
              (progress) => {
                console.log('Animation loading progress:', (progress.loaded / progress.total * 100) + '%');
              }, 
              reject);
          });
          
          if (animationGltf.animations && animationGltf.animations.length > 0) {
            animationClips = [...animationClips, ...animationGltf.animations];
          }
        } catch (error) {
          console.warn('Failed to load separate animations:', error);
          
          // Try loading individual FBX animation files as fallback
          await this.loadIndividualAnimations(animationClips);
        }
      } else {
        // If no separate animations URL, try loading individual FBX files
        await this.loadIndividualAnimations(animationClips);
      }
      
      // Setup animations
      if (animationClips && animationClips.length > 0) {
        this.mixer = new THREE.AnimationMixer(this.avatar);
        
        animationClips.forEach((clip) => {
          const action = this.mixer.clipAction(clip);
          const clipName = clip.name.toLowerCase();
          
          if (clipName.includes('idle')) {
            this.idleAction = action;
            console.log('Found idle animation:', clip.name);
          } else if (clipName.includes('talk') || clipName.includes('speaking')) {
            this.talkingAction = action;
            console.log('Found talking animation:', clip.name);
          }
        });
        
        // Start with idle animation
        if (this.idleAction) {
          this.idleAction.play();
          this.currentAnimation = 'idle';
          console.log('Started idle animation');
        } else if (animationClips.length > 0) {
          // Fallback to first animation if no idle found
          const fallbackAction = this.mixer.clipAction(animationClips[0]);
          fallbackAction.play();
          this.idleAction = fallbackAction;
          this.currentAnimation = 'idle';
          console.log('Using fallback animation:', animationClips[0].name);
        }
      } else {
        console.log('No animations found, avatar will be static');
      }
      
      // Find morph targets for lipsync
      this.readyPlayerMeController = new ReadyPlayerMeAvatar(this);
      this.morphTargets = this.readyPlayerMeController.findMorphTargets(this.avatar);
      this.readyPlayerMeController.setupExpressionController(this.avatar, this.morphTargets);
      
      // Position avatar - make it bigger
      this.avatar.position.set(0, 0, 0);
      this.avatar.scale.set(1.5, 1.5, 1.5); // Make avatar 50% bigger
      
      console.log('Avatar loaded successfully from local assets');
      
    } catch (error) {
      console.error('Failed to load avatar from local assets:', error);
      // Fallback: create a simple capsule as placeholder
      this.createFallbackAvatar();
    }
  }
  
  async loadIndividualAnimations(animationClips) {
    // Try to load individual FBX animation files
    const fbxLoader = new THREE.FBXLoader();
    const animationFiles = [
      { name: 'idle', path: 'assets/avatars/animations/Idle.fbx' },
      { name: 'talking', path: 'assets/avatars/animations/Talking.fbx' },
      { name: 'thinking', path: 'assets/avatars/animations/Thinking.fbx' }
    ];
    
    for (const animFile of animationFiles) {
      try {
        console.log(`Loading ${animFile.name} animation from ${animFile.path}...`);
        const fbx = await new Promise((resolve, reject) => {
          fbxLoader.load(animFile.path,
            (object) => resolve(object),
            (progress) => {
              console.log(`${animFile.name} loading progress:`, (progress.loaded / progress.total * 100) + '%');
            },
            (error) => reject(error)
          );
        });
        
        if (fbx.animations && fbx.animations.length > 0) {
          // Rename animation to match our expected names
          fbx.animations.forEach(clip => {
            clip.name = animFile.name;
          });
          animationClips.push(...fbx.animations);
          console.log(`Successfully loaded ${animFile.name} animation`);
        }
      } catch (error) {
        console.warn(`Failed to load ${animFile.name} animation:`, error);
      }
    }
    
    return animationClips;
  }
  
  createFallbackAvatar() {
    console.log('Creating fallback avatar...');
    
    // Create a simple but nice-looking robot avatar
    const group = new THREE.Group();
    
    // Head
    const headGeometry = new THREE.SphereGeometry(0.5, 32, 32);
    const headMaterial = new THREE.MeshStandardMaterial({ 
      color: 0x8cc8ff, 
      metalness: 0.3,
      roughness: 0.4
    });
    const head = new THREE.Mesh(headGeometry, headMaterial);
    head.position.set(0, 1.5, 0);
    group.add(head);
    
    // Eyes
    const eyeGeometry = new THREE.SphereGeometry(0.1, 16, 16);
    const eyeMaterial = new THREE.MeshStandardMaterial({ color: 0x333333 });
    
    const leftEye = new THREE.Mesh(eyeGeometry, eyeMaterial);
    leftEye.position.set(-0.2, 1.6, 0.4);
    group.add(leftEye);
    
    const rightEye = new THREE.Mesh(eyeGeometry, eyeMaterial);
    rightEye.position.set(0.2, 1.6, 0.4);
    group.add(rightEye);
    
    // Mouth (simple line)
    const mouthGeometry = new THREE.CapsuleGeometry(0.02, 0.3, 4, 8);
    const mouthMaterial = new THREE.MeshStandardMaterial({ color: 0x333333 });
    const mouth = new THREE.Mesh(mouthGeometry, mouthMaterial);
    mouth.position.set(0, 1.3, 0.4);
    mouth.rotation.z = Math.PI / 2;
    group.add(mouth);
    
    // Body
    const bodyGeometry = new THREE.CapsuleGeometry(0.4, 1.0, 4, 8);
    const bodyMaterial = new THREE.MeshStandardMaterial({ 
      color: 0x6ba3ff,
      metalness: 0.2,
      roughness: 0.5
    });
    const body = new THREE.Mesh(bodyGeometry, bodyMaterial);
    body.position.set(0, 0.5, 0);
    group.add(body);
    
    this.avatar = group;
    this.scene.add(this.avatar);
    
    // Simple animation for fallback avatar
    this.fallbackAnimation = () => {
      if (this.avatar) {
        const time = Date.now() * 0.001;
        this.avatar.rotation.y = Math.sin(time * 0.5) * 0.1; // Gentle swaying
        head.rotation.y = Math.sin(time) * 0.2; // Head movement
      }
    };
  }
  
  setupLipsync() {
    this.audioElement = document.createElement('audio');
    this.audioElement.crossOrigin = 'anonymous';
    
    // Add variables for better lipsync control
    this.isSpeaking = false;
    this.morphTargetResetTimer = null;
    this.lastVisemeTime = 0;
    this.smoothingFactor = 0.15; // Slower transitions for smoother animation
    
    // Enhanced lipsync with better clipping prevention
    this.rhubarbLipsync.onViseme = (viseme, value) => {
      this.lastVisemeTime = Date.now();
      this.isSpeaking = true;
      
      if (this.morphTargets[viseme]) {
        this.morphTargets[viseme].forEach((target) => {
          // Use smoother interpolation with clamping
          const currentValue = target.morphTargetInfluences[target.index];
          const targetValue = Math.max(0, Math.min(1, value)); // Clamp between 0-1
          target.morphTargetInfluences[target.index] = THREE.MathUtils.lerp(
            currentValue,
            targetValue,
            this.smoothingFactor
          );
        });
      }
      
      // Clear any pending reset timer
      if (this.morphTargetResetTimer) {
        clearTimeout(this.morphTargetResetTimer);
        this.morphTargetResetTimer = null;
      }
    };

    this.rhubarbLipsync.onStop = () => {
      this.gracefulStopLipsync();
    };
    
    // Monitor for speech end with timeout
    this.speechMonitor = setInterval(() => {
      if (this.isSpeaking && Date.now() - this.lastVisemeTime > 500) {
        this.gracefulStopLipsync();
      }
    }, 100);
  }
  
  gracefulStopLipsync() {
    this.isSpeaking = false;
    
    // Gradually fade out morph targets to prevent clipping
    const fadeOutMorphTargets = () => {
      let hasActiveTargets = false;
      
      Object.values(this.morphTargets).forEach((targets) => {
        targets.forEach((target) => {
          const currentValue = target.morphTargetInfluences[target.index];
          if (currentValue > 0.01) {
            // Gradually reduce the influence
            target.morphTargetInfluences[target.index] = THREE.MathUtils.lerp(
              currentValue,
              0,
              0.1 // Slower fade out for smoother transition
            );
            hasActiveTargets = true;
          } else {
            // Snap to zero when very close
            target.morphTargetInfluences[target.index] = 0;
          }
        });
      });
      
      if (hasActiveTargets) {
        this.morphTargetResetTimer = setTimeout(fadeOutMorphTargets, 16); // ~60fps
      }
    };
    
    fadeOutMorphTargets();
  }

  // Enhanced method to sync lipsync with real-time text generation
  async startSpeakingWithTextStream(textStream, onTextUpdate = null) {
    try {
      // Start talking animation immediately
      this.switchAnimation('talking');
      
      let fullText = '';
      let currentChunk = '';
      const textChunks = [];
      
      // Process text stream and generate speech in chunks
      for await (const chunk of textStream) {
        fullText += chunk;
        currentChunk += chunk;
        
        // Update UI with new text if callback provided
        if (onTextUpdate) {
          onTextUpdate(fullText);
        }
        
        // When we have enough text for a sentence/phrase, generate speech
        if (this.shouldGenerateSpeech(currentChunk)) {
          textChunks.push(currentChunk.trim());
          currentChunk = '';
        }
      }
      
      // Add any remaining text
      if (currentChunk.trim()) {
        textChunks.push(currentChunk.trim());
      }
      
      // Generate and play speech for all chunks
      await this.playTextChunksSequentially(textChunks);
      
    } catch (error) {
      console.error('Failed to speak with text stream:', error);
      this.switchAnimation('idle');
    }
  }
  
  shouldGenerateSpeech(text) {
    // Generate speech when we hit sentence boundaries or have enough text
    const sentenceEnders = /[.!?]\s/;
    const hasEnoughText = text.length > 50;
    return sentenceEnders.test(text) || hasEnoughText;
  }
  
  async playTextChunksSequentially(textChunks) {
    for (let i = 0; i < textChunks.length; i++) {
      const chunk = textChunks[i];
      if (!chunk.trim()) continue;
      
      try {
        // Generate speech for this chunk
        const audioBlob = await this.generateSpeech(chunk);
        const audioUrl = URL.createObjectURL(audioBlob);
        
        // Play this chunk
        await this.playAudioWithLipsync(audioUrl);
        
        // Cleanup
        URL.revokeObjectURL(audioUrl);
        
        // Small pause between chunks for natural flow
        if (i < textChunks.length - 1) {
          await new Promise(resolve => setTimeout(resolve, 200));
        }
        
      } catch (error) {
        console.error(`Failed to play chunk ${i}:`, error);
      }
    }
    
    // Return to idle when done
    this.switchAnimation('idle');
  }
  
  async playAudioWithLipsync(audioUrl) {
    return new Promise((resolve, reject) => {
      this.audioElement.src = audioUrl;
      this.rhubarbLipsync.connectAudio(this.audioElement);
      
      this.audioElement.onended = () => {
        resolve();
      };
      
      this.audioElement.onerror = (error) => {
        reject(error);
      };
      
      this.audioElement.play().catch(reject);
    });
  }
  
  setupControls() {
    // Simple mouse controls for camera
    let isMouseDown = false;
    let mouseX = 0;
    let mouseY = 0;
    
    this.container.addEventListener('mousedown', (event) => {
      isMouseDown = true;
      mouseX = event.clientX;
      mouseY = event.clientY;
    });
    
    this.container.addEventListener('mousemove', (event) => {
      if (!isMouseDown) return;
      
      const deltaX = event.clientX - mouseX;
      const deltaY = event.clientY - mouseY;
      
      // Rotate camera around avatar
      const spherical = new THREE.Spherical();
      spherical.setFromVector3(this.camera.position);
      spherical.theta -= deltaX * 0.01;
      spherical.phi += deltaY * 0.01;
      spherical.phi = Math.max(0.1, Math.min(Math.PI - 0.1, spherical.phi));
      
      this.camera.position.setFromSpherical(spherical);
      this.camera.lookAt(0, 1.5, 0);
      
      mouseX = event.clientX;
      mouseY = event.clientY;
    });
    
    this.container.addEventListener('mouseup', () => {
      isMouseDown = false;
    });
    
    // Zoom with mouse wheel - adjusted for larger avatar
    this.container.addEventListener('wheel', (event) => {
      const distance = this.camera.position.distanceTo(new THREE.Vector3(0, 1.5, 0));
      const newDistance = Math.max(0.8, Math.min(8, distance + event.deltaY * 0.01)); // Closer min distance
      
      this.camera.position.normalize().multiplyScalar(newDistance);
      this.camera.position.y = Math.max(0.5, this.camera.position.y);
      this.camera.lookAt(0, 1.5, 0);
    });
  }
  
  animate() {
    requestAnimationFrame(() => this.animate());
    
    const delta = this.clock.getDelta();
    
    // Update animations
    if (this.mixer) {
      this.mixer.update(delta);
    }
    
    // Update fallback animation if using fallback avatar
    if (this.fallbackAnimation) {
      this.fallbackAnimation();
    }
    
    // Update lipsync using Rhubarb
    if (this.audioElement && !this.audioElement.paused) {
      this.rhubarbLipsync.processAudio();
      this.updateMorphTargets();
    }
    
    // Render
    this.renderer.render(this.scene, this.camera);
  }
  
  updateMorphTargets() {
    if (!this.readyPlayerMeController) return;
    
    const viseme = this.rhubarbLipsync.getCurrentViseme();
    const intensity = this.rhubarbLipsync.getCurrentIntensity();
    
    if (intensity > 0.1) {
      this.readyPlayerMeController.updateLipsync(viseme, intensity);
    } else {
      this.readyPlayerMeController.updateLipsync('viseme_sil', 1.0);
    }
  }
  
  async speak(text, options = {}) {
    if (!this.options.elevenlabsApiKey) {
      console.warn('ElevenLabs API key not provided');
      return;
    }
    
    try {
      // If text is provided as a stream or has streaming capability
      if (options.useStreaming && text.length > 100) {
        // Break text into smaller chunks and stream
        const textStream = this.createTextStream(text);
        await this.startSpeakingWithTextStream(textStream, options.onTextUpdate);
      } else {
        // Traditional approach for shorter text
        const audioBlob = await this.generateSpeech(text);
        const audioUrl = URL.createObjectURL(audioBlob);
        
        // Switch to talking animation
        this.switchAnimation('talking');
        
        // Play audio with enhanced lipsync
        await this.playAudioWithLipsync(audioUrl);
        
        // Switch back to idle when done
        this.switchAnimation('idle');
        URL.revokeObjectURL(audioUrl);
      }
      
    } catch (error) {
      console.error('Failed to speak:', error);
      this.switchAnimation('idle');
    }
  }
  
  // Helper method to create a text stream from static text
  async* createTextStream(text, chunkSize = 20) {
    for (let i = 0; i < text.length; i += chunkSize) {
      yield text.slice(i, i + chunkSize);
      // Small delay to simulate streaming
      await new Promise(resolve => setTimeout(resolve, 50));
    }
  }
  
  async generateSpeech(text) {
    const response = await fetch(`https://api.elevenlabs.io/v1/text-to-speech/${this.options.voice}`, {
      method: 'POST',
      headers: {
        'Accept': 'audio/mpeg',
        'Content-Type': 'application/json',
        'xi-api-key': this.options.elevenlabsApiKey
      },
      body: JSON.stringify({
        text: text,
        model_id: 'eleven_monolingual_v1',
        voice_settings: {
          stability: 0.5,
          similarity_boost: 0.75
        }
      })
    });
    
    if (!response.ok) {
      throw new Error(`ElevenLabs API error: ${response.status}`);
    }
    
    return await response.blob();
  }
  
  switchAnimation(type) {
    if (!this.mixer) return;
    
    if (type === 'talking' && this.talkingAction && this.currentAnimation !== 'talking') {
      if (this.idleAction) this.idleAction.fadeOut(0.5);
      this.talkingAction.reset().fadeIn(0.5).play();
      this.currentAnimation = 'talking';
    } else if (type === 'idle' && this.idleAction && this.currentAnimation !== 'idle') {
      if (this.talkingAction) this.talkingAction.fadeOut(0.5);
      this.idleAction.reset().fadeIn(0.5).play();
      this.currentAnimation = 'idle';
    }
  }
  
  onWindowResize() {
    const width = this.container.clientWidth;
    const height = this.container.clientHeight;
    
    this.camera.aspect = width / height;
    this.camera.updateProjectionMatrix();
    this.renderer.setSize(width, height);
  }
  
  setElevenlabsApiKey(apiKey) {
    this.options.elevenlabsApiKey = apiKey;
  }
  
  destroy() {
    // Clean up timers
    if (this.morphTargetResetTimer) {
      clearTimeout(this.morphTargetResetTimer);
    }
    if (this.speechMonitor) {
      clearInterval(this.speechMonitor);
    }
    
    // Clean up controllers
    if (this.readyPlayerMeController) {
      this.readyPlayerMeController.destroy();
    }
    if (this.rhubarbLipsync) {
      this.rhubarbLipsync.destroy();
    }
    
    // Clean up renderer
    if (this.renderer) {
      this.container.removeChild(this.renderer.domElement);
    }
    
    // Clean up audio
    if (this.audioElement) {
      this.audioElement.pause();
      this.audioElement.src = '';
    }
  }
}

// Export for global use
window.AvatarManager = AvatarManager;
