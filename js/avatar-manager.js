// Three.js Avatar Manager for VerzTec Chatbot
// Integrates Ready Player Me avatar with lipsync and ElevenLabs TTS

class AvatarManager {
  constructor(containerId, options = {}) {
    this.container = document.getElementById(containerId);
    this.options = {
      avatarUrl: options.avatarUrl || 'assets/avatars/models/64f1a714fe61576b46f27ca2.glb',
      animationsUrl: options.animationsUrl || 'assets/avatars/models/animations.glb',
      elevenlabsApiKey: options.elevenlabsApiKey || 'sk_72283c30a844b3d198dda76a38373741c8968217a9472ae7',
      voice: options.voice || 'EXAVITQu4vr4xnSDxMaL', // Bella voice
      ...options
    };
    
    this.scene = null;
    this.camera = null;
    this.renderer = null;
    this.avatar = null;
    this.mixer = null;
    this.clock = new THREE.Clock();
    this.rhubarbLipsync = new RhubarbLipsync();
    this.audioElement = null;
    this.morphTargets = {};
    this.isInitialized = false;
    this.readyPlayerMeController = null;
    
    // Animation state
    this.idleAction = null;
    this.talkingAction = null;
    this.currentAnimation = null;
    
    // Speech state
    this.isSpeaking = false;
    this.speechStartTime = 0;
    
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
    this.scene.background = null; // Transparent background
    
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
    this.camera.position.set(0, 1.6, 1.8);
    this.camera.lookAt(0, 1.5, 0);
  }
  
  setupRenderer() {
    this.renderer = new THREE.WebGLRenderer({ 
      antialias: true,
      alpha: true
    });
    this.renderer.setSize(this.container.clientWidth, this.container.clientHeight);
    this.renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    this.renderer.shadowMap.enabled = true;
    this.renderer.shadowMap.type = THREE.PCFSoftShadowMap;
    this.renderer.toneMapping = THREE.ACESFilmicToneMapping;
    this.renderer.toneMappingExposure = 1.2;
    this.renderer.setClearColor(0x000000, 0);
    
    this.container.appendChild(this.renderer.domElement);
    
    window.addEventListener('resize', () => this.onWindowResize());
  }
  
  async loadAvatar() {
    const loader = new THREE.GLTFLoader();
    
    try {
      const gltf = await new Promise((resolve, reject) => {
        loader.load(this.options.avatarUrl, resolve, 
          (progress) => {
            console.log('Avatar loading progress:', (progress.loaded / progress.total * 100) + '%');
          }, 
          reject);
      });
      
      this.avatar = gltf.scene;
      this.scene.add(this.avatar);
      
      // Load animations
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
        }
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
        }
      }
      
      // Setup Ready Player Me controller
      this.readyPlayerMeController = new ReadyPlayerMeAvatar(this);
      this.morphTargets = this.readyPlayerMeController.findMorphTargets(this.avatar);
      this.readyPlayerMeController.setupExpressionController(this.avatar, this.morphTargets);
      
      // Position avatar
      this.avatar.position.set(0, 0, 0);
      this.avatar.scale.set(1.5, 1.5, 1.5);
      
      console.log('Avatar loaded successfully');
      
    } catch (error) {
      console.error('Failed to load avatar:', error);
    }
  }
  
  setupLipsync() {
    // Create main audio element for playback
    this.audioElement = document.createElement('audio');
    this.audioElement.crossOrigin = 'anonymous';
    this.audioElement.preload = 'auto';
    this.audioElement.volume = 1.0;
    this.audioElement.muted = false;
    
    // Add the audio element to the DOM (hidden) to ensure it can play
    this.audioElement.style.display = 'none';
    document.body.appendChild(this.audioElement);
    
    console.log('Audio element created and added to DOM');
    
    // Track lipsync connection state
    this.audioConnectedToLipsync = false;
    this.lipsyncEnabled = true; // Flag to enable/disable lipsync
    
    // Add a click listener to the document to enable audio context on first interaction
    const enableAudioContext = () => {
      if (this.rhubarbLipsync.audioContext && this.rhubarbLipsync.audioContext.state === 'suspended') {
        this.rhubarbLipsync.audioContext.resume().then(() => {
          console.log('Audio context resumed after user interaction');
          
          // Try to connect audio to lipsync system if not already connected and lipsync is enabled
          if (!this.audioConnectedToLipsync && this.lipsyncEnabled) {
            this.connectAudioToLipsync();
          }
        });
      }
      document.removeEventListener('click', enableAudioContext);
    };
    
    document.addEventListener('click', enableAudioContext);
    
    // Attempt initial connection to lipsync (but don't fail if it doesn't work)
    if (this.lipsyncEnabled) {
      this.connectAudioToLipsync();
    }
    
    // Set up continuous lipsync updates
    this.lipsyncUpdateInterval = setInterval(() => {
      if (this.rhubarbLipsync.isPlaying) {
        this.rhubarbLipsync.update(0.016); // ~60 FPS
      }
    }, 16);
    
    // Add event listeners for debugging
    this.audioElement.addEventListener('play', () => {
      console.log('🎵 Audio element: play event fired');
    });
    
    this.audioElement.addEventListener('playing', () => {
      console.log('▶️ Audio element: playing event fired');
    });
    
    this.audioElement.addEventListener('pause', () => {
      console.log('⏸️ Audio element: pause event fired');
    });
    
    this.audioElement.addEventListener('ended', () => {
      console.log('🏁 Audio element: ended event fired');
    });
    
    this.audioElement.addEventListener('error', (e) => {
      console.error('❌ Audio element error:', e);
    });
    
    console.log('Lipsync system initialized');
  }
  
  // Helper method to connect audio to lipsync system
  connectAudioToLipsync() {
    try {
      if (this.rhubarbLipsync.connectAudio(this.audioElement)) {
        console.log('✅ Audio connected to lipsync system');
        this.audioConnectedToLipsync = true;
      } else {
        console.warn('⚠️ Failed to connect audio to lipsync system');
        this.audioConnectedToLipsync = false;
      }
    } catch (error) {
      console.error('❌ Error connecting audio to lipsync:', error);
      this.audioConnectedToLipsync = false;
    }
  }
  
  setupControls() {
    // Setup any additional controls if needed
  }
  
  animate() {
    requestAnimationFrame(() => this.animate());
    
    const deltaTime = this.clock.getDelta();
    
    // Update animations
    if (this.mixer) {
      this.mixer.update(deltaTime);
    }
    
    // Update lipsync
    this.updateMorphTargets();
    
    // Render
    this.renderer.render(this.scene, this.camera);
  }
  
  updateMorphTargets() {
    if (!this.readyPlayerMeController) return;
    
    // Get current viseme and intensity from lipsync
    const viseme = this.rhubarbLipsync.getCurrentViseme();
    const intensity = this.rhubarbLipsync.getCurrentIntensity();
    
    // Apply lipsync morphs with proper intensity
    if (this.isSpeaking && intensity > 0.05) {
      this.readyPlayerMeController.updateLipsync(viseme, intensity * 0.8);
    } else if (this.isSpeaking) {
      // Keep mouth slightly open during talking animation
      this.readyPlayerMeController.updateLipsync('viseme_aa', 0.2);
    } else {
      // Idle state - closed mouth
      this.readyPlayerMeController.updateLipsync('viseme_sil', 1.0);
    }
  }
  
  switchAnimation(type) {
    if (!this.mixer) return;
    
    console.log(`Switching animation to: ${type}`);
    
    if (type === 'talking' && this.talkingAction && this.currentAnimation !== 'talking') {
      if (this.idleAction) {
        this.idleAction.fadeOut(0.3);
      }
      this.talkingAction.reset().fadeIn(0.3).play();
      this.talkingAction.setLoop(THREE.LoopRepeat);
      this.currentAnimation = 'talking';
      console.log('Switched to talking animation');
    } else if (type === 'idle' && this.idleAction && this.currentAnimation !== 'idle') {
      if (this.talkingAction) {
        this.talkingAction.fadeOut(0.3);
      }
      this.idleAction.reset().fadeIn(0.3).play();
      this.idleAction.setLoop(THREE.LoopRepeat);
      this.currentAnimation = 'idle';
      console.log('Switched to idle animation');
    }
  }
  
  // Type text with a realistic typing effect
  async typeText(text, onTextUpdate, delay = 30) {
    let currentText = '';
    for (let i = 0; i < text.length; i++) {
      currentText += text[i];
      onTextUpdate(currentText);
      await new Promise(resolve => setTimeout(resolve, delay));
    }
  }
  
  // Play audio with proper synchronization of animation and lipsync
  async playAudioWithSynchronization(audioUrl) {
    if (!this.audioElement) {
      console.error('Audio element not initialized');
      return;
    }

    try {
      // Set up audio
      this.audioElement.src = audioUrl;
      
      // Wait for audio to be ready
      await new Promise((resolve, reject) => {
        const timeoutId = setTimeout(() => {
          reject(new Error('Audio load timeout'));
        }, 10000);
        
        this.audioElement.addEventListener('loadeddata', () => {
          clearTimeout(timeoutId);
          resolve();
        }, { once: true });
        
        this.audioElement.addEventListener('error', (e) => {
          clearTimeout(timeoutId);
          reject(e);
        }, { once: true });
        
        this.audioElement.load();
      });

      // Start talking animation and speaking state
      this.switchAnimation('talking');
      this.isSpeaking = true;
      this.speechStartTime = Date.now();
      
      // Initialize lipsync
      this.rhubarbLipsync.reset();
      await this.rhubarbLipsync.loadAudio(audioUrl);
      
      // Start audio playback
      const playPromise = this.audioElement.play();
      if (playPromise !== undefined) {
        await playPromise;
      }
      
      // Start lipsync
      this.rhubarbLipsync.start();
      
      // Wait for audio to finish
      await new Promise((resolve) => {
        this.audioElement.addEventListener('ended', () => {
          // Keep talking animation for a bit longer for natural feel
          setTimeout(() => {
            this.isSpeaking = false;
            this.switchAnimation('idle');
            this.rhubarbLipsync.stop();
            resolve();
          }, 300);
        }, { once: true });
      });
      
    } catch (error) {
      console.error('Audio playback failed:', error);
      this.isSpeaking = false;
      this.switchAnimation('idle');
      throw error;
    }
  }
  
  // Play audio with fallback approaches to ensure sound works
  async playAudioOnly(audioUrl) {
    if (!this.audioElement) {
      console.error('Audio element not initialized');
      return;
    }

    console.log('🎵 Starting audio playback for:', audioUrl);

    try {
      // Method 1: Try with lipsync connection first
      if (this.audioConnectedToLipsync && this.lipsyncEnabled) {
        console.log('🎭 Attempting playback with lipsync...');
        
        try {
          await this.playWithLipsync(audioUrl);
          console.log('✅ Audio with lipsync completed successfully');
          return;
        } catch (lipsyncError) {
          console.warn('⚠️ Lipsync audio failed:', lipsyncError);
          // Don't return, try fallback methods
        }
      }

      // Method 2: Try with simple audio element (no lipsync)
      console.log('🔊 Attempting simple audio playback...');
      try {
        await this.playAudioDirect(audioUrl);
        console.log('✅ Simple audio playback completed successfully');
        return;
      } catch (simpleError) {
        console.warn('⚠️ Simple audio failed:', simpleError);
      }

      // Method 3: Last resort - completely new audio element
      console.log('🆘 Attempting with new audio element...');
      await this.playAudioNewElement(audioUrl);
      console.log('✅ New element audio playback completed successfully');

    } catch (error) {
      console.error('❌ All audio playback methods failed:', error);
      this.isSpeaking = false;
      this.switchAnimation('idle');
      throw error;
    }
  }

  // Method 1: Play with lipsync connection
  async playWithLipsync(audioUrl) {
    // Ensure audio context is running
    if (this.rhubarbLipsync.audioContext && this.rhubarbLipsync.audioContext.state === 'suspended') {
      console.log('Resuming audio context...');
      await this.rhubarbLipsync.audioContext.resume();
    }

    // Set up audio
    this.audioElement.src = audioUrl;
    this.audioElement.volume = 1.0;
    this.audioElement.muted = false;
    
    // Wait for audio to be ready
    await new Promise((resolve, reject) => {
      const timeoutId = setTimeout(() => {
        reject(new Error('Audio load timeout'));
      }, 10000);
      
      this.audioElement.addEventListener('loadeddata', () => {
        clearTimeout(timeoutId);
        console.log('🎵 Audio loaded for lipsync playback');
        resolve();
      }, { once: true });
      
      this.audioElement.addEventListener('error', (e) => {
        clearTimeout(timeoutId);
        reject(e);
      }, { once: true });
      
      this.audioElement.load();
    });

    // Initialize lipsync
    this.rhubarbLipsync.reset();
    this.rhubarbLipsync.start();
    
    // Start audio playback
    const playPromise = this.audioElement.play();
    if (playPromise !== undefined) {
      await playPromise;
    }
    
    // Wait for audio to finish
    await new Promise((resolve) => {
      this.audioElement.addEventListener('ended', () => {
        setTimeout(() => {
          this.isSpeaking = false;
          this.switchAnimation('idle');
          this.rhubarbLipsync.stop();
          resolve();
        }, 300);
      }, { once: true });
    });
  }

  // Method 2: Play with direct audio element (no lipsync)
  async playAudioDirect(audioUrl) {
    // Disconnect from lipsync temporarily
    if (this.audioConnectedToLipsync && this.rhubarbLipsync.audioSource) {
      try {
        this.rhubarbLipsync.audioSource.disconnect();
        console.log('� Disconnected from lipsync for direct playback');
      } catch (e) {
        console.warn('Could not disconnect from lipsync:', e);
      }
    }

    // Set up audio for direct playback
    this.audioElement.src = audioUrl;
    this.audioElement.volume = 1.0;
    this.audioElement.muted = false;
    
    // Wait for audio to be ready
    await new Promise((resolve, reject) => {
      const timeoutId = setTimeout(() => {
        reject(new Error('Audio load timeout'));
      }, 10000);
      
      this.audioElement.addEventListener('loadeddata', () => {
        clearTimeout(timeoutId);
        console.log('🎵 Audio loaded for direct playback');
        resolve();
      }, { once: true });
      
      this.audioElement.addEventListener('error', (e) => {
        clearTimeout(timeoutId);
        reject(e);
      }, { once: true });
      
      this.audioElement.load();
    });

    // Play audio directly
    const playPromise = this.audioElement.play();
    if (playPromise !== undefined) {
      await playPromise;
    }
    
    console.log('🎵 Direct audio playback started');
    
    // Wait for audio to finish
    await new Promise((resolve) => {
      this.audioElement.addEventListener('ended', () => {
        setTimeout(() => {
          this.isSpeaking = false;
          this.switchAnimation('idle');
          
          // Reconnect to lipsync if it was connected before
          if (this.lipsyncEnabled && !this.audioConnectedToLipsync) {
            this.connectAudioToLipsync();
          }
          
          resolve();
        }, 300);
      }, { once: true });
    });
  }

  // Method 3: Play with completely new audio element
  async playAudioNewElement(audioUrl) {
    const tempAudio = document.createElement('audio');
    tempAudio.crossOrigin = 'anonymous';
    tempAudio.volume = 1.0;
    tempAudio.muted = false;
    tempAudio.src = audioUrl;
    
    // Add to DOM temporarily
    tempAudio.style.display = 'none';
    document.body.appendChild(tempAudio);
    
    console.log('🆕 New audio element created for playback');
    
    // Play the audio
    await tempAudio.play();
    console.log('🎵 New element audio playing...');
    
    // Wait for completion
    await new Promise((resolve) => {
      tempAudio.addEventListener('ended', () => {
        console.log('🏁 New element audio finished');
        document.body.removeChild(tempAudio);
        
        setTimeout(() => {
          this.isSpeaking = false;
          this.switchAnimation('idle');
          resolve();
        }, 300);
      });
    });
  }

  // Fallback method to play audio without lipsync if needed
  async playAudioSimple(audioUrl) {
    console.log('🎵 Playing audio with simple method (no lipsync)...');
    
    try {
      // Create a temporary audio element that's not connected to Web Audio API
      const tempAudio = document.createElement('audio');
      tempAudio.crossOrigin = 'anonymous';
      tempAudio.volume = 1.0;
      tempAudio.muted = false;
      tempAudio.src = audioUrl;
      
      // Add to DOM temporarily
      tempAudio.style.display = 'none';
      document.body.appendChild(tempAudio);
      
      console.log('🔧 Simple audio element created');
      
      // Play the audio
      await tempAudio.play();
      console.log('▶️ Simple audio playing...');
      
      // Wait for completion
      await new Promise((resolve) => {
        tempAudio.addEventListener('ended', () => {
          console.log('🏁 Simple audio finished');
          document.body.removeChild(tempAudio);
          resolve();
        });
      });
      
    } catch (error) {
      console.error('❌ Simple audio playback failed:', error);
      throw error;
    }
  }

  async speak(text, options = {}) {
    if (!this.options.elevenlabsApiKey) {
      console.warn('ElevenLabs API key not provided');
      return;
    }

    try {
      // Generate speech audio
      const audioBlob = await this.generateSpeech(text);
      const audioUrl = URL.createObjectURL(audioBlob);
      
      // Play audio with proper synchronization
      await this.playAudioWithSynchronization(audioUrl);
      
      URL.revokeObjectURL(audioUrl);
      
    } catch (error) {
      console.error('Failed to speak:', error);
      this.switchAnimation('idle');
      this.isSpeaking = false;
    }
  }
  
  // Method to start speaking immediately and stream text in sync
  async speakWithTextStream(text, onTextUpdate = null) {
    // Debug avatar state
    this.debugAvatarState();
    
    if (!this.options.elevenlabsApiKey) {
      console.warn('ElevenLabs API key not provided, showing text only');
      if (onTextUpdate) {
        // Stream the text letter by letter for typing effect
        await this.typeText(text, onTextUpdate);
      }
      return;
    }

    try {
      // Start talking animation immediately
      console.log('🎭 Starting talking animation...');
      this.switchAnimation('talking');
      this.isSpeaking = true;
      this.speechStartTime = Date.now();
      
      // Start generating speech audio
      console.log('🎤 Generating speech for:', text.substring(0, 50) + '...');
      const audioPromise = this.generateSpeech(text);
      
      // Start displaying text with typing effect in parallel
      const textPromise = onTextUpdate ? this.typeText(text, onTextUpdate) : Promise.resolve();
      
      // Wait for both audio generation and text display to complete
      console.log('⏳ Waiting for audio generation and text display...');
      const [audioBlob] = await Promise.all([audioPromise, textPromise]);
      console.log('🎵 Audio generated, blob size:', audioBlob.size);
      
      const audioUrl = URL.createObjectURL(audioBlob);
      
      // Try to play audio with our robust method
      console.log('🔊 Playing audio with multiple fallback methods...');
      await this.playAudioOnly(audioUrl);
      
      // Clean up
      URL.revokeObjectURL(audioUrl);
      console.log('✅ Speech completed successfully');
      
    } catch (error) {
      console.error('❌ Speech failed:', error);
      this.switchAnimation('idle');
      this.isSpeaking = false;
      
      // Show error to user if text display is available
      if (onTextUpdate) {
        onTextUpdate(text + '\n\n[Audio playback failed, but text is displayed]');
      }
    }
  }
  
  async generateSpeech(text) {
    try {
      console.log('Generating speech for:', text.substring(0, 50) + '...');
      console.log('Using voice:', this.options.voice);
      
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
        const errorText = await response.text();
        console.error('ElevenLabs API error:', response.status, errorText);
        throw new Error(`ElevenLabs API error: ${response.status} - ${errorText}`);
      }
      
      const audioBlob = await response.blob();
      console.log('Speech generated successfully, blob size:', audioBlob.size);
      return audioBlob;
      
    } catch (error) {
      console.error('Speech generation failed:', error);
      throw error;
    }
  }
  
  // Test the ElevenLabs API key
  async testApiKey() {
    if (!this.options.elevenlabsApiKey) {
      return { success: false, error: 'No API key provided' };
    }
    
    try {
      const response = await fetch('https://api.elevenlabs.io/v1/voices', {
        method: 'GET',
        headers: {
          'xi-api-key': this.options.elevenlabsApiKey
        }
      });
      
      if (!response.ok) {
        const errorText = await response.text();
        return { success: false, error: `API key test failed: ${response.status} - ${errorText}` };
      }
      
      const data = await response.json();
      console.log('API key test successful. Available voices:', data.voices.length);
      return { success: true, voices: data.voices };
      
    } catch (error) {
      return { success: false, error: `API key test error: ${error.message}` };
    }
  }
  
  // Simple test method for debugging
  async testSpeechSimple(text = "Hello, this is a test") {
    console.log('=== Testing Simple Speech ===');
    this.debugAvatarState();
    
    try {
      // Test API key first
      const apiTest = await this.testApiKey();
      console.log('API Test Result:', apiTest);
      
      if (!apiTest.success) {
        console.error('API key test failed:', apiTest.error);
        return;
      }
      
      // Start talking animation
      console.log('Starting talking animation...');
      this.switchAnimation('talking');
      this.isSpeaking = true;
      
      // Generate speech
      console.log('Generating speech...');
      const audioBlob = await this.generateSpeech(text);
      console.log('Speech generated, size:', audioBlob.size);
      
      // Create audio URL
      const audioUrl = URL.createObjectURL(audioBlob);
      console.log('Audio URL created:', audioUrl);
      
      // Set up audio element
      this.audioElement.src = audioUrl;
      console.log('Audio element src set');
      
      // Wait for load
      await new Promise((resolve, reject) => {
        const timeoutId = setTimeout(() => reject(new Error('Load timeout')), 10000);
        
        this.audioElement.addEventListener('loadeddata', () => {
          clearTimeout(timeoutId);
          console.log('Audio loaded successfully');
          resolve();
        }, { once: true });
        
        this.audioElement.addEventListener('error', (e) => {
          clearTimeout(timeoutId);
          console.error('Audio load error:', e);
          reject(e);
        }, { once: true });
        
        this.audioElement.load();
      });
      
      // Start lipsync
      this.rhubarbLipsync.reset();
      this.rhubarbLipsync.start();
      console.log('Lipsync started');
      
      // Play audio
      console.log('Playing audio...');
      await this.audioElement.play();
      console.log('Audio is playing');
      
      // Wait for end
      await new Promise((resolve) => {
        this.audioElement.addEventListener('ended', () => {
          console.log('Audio ended');
          setTimeout(() => {
            this.isSpeaking = false;
            this.switchAnimation('idle');
            this.rhubarbLipsync.stop();
            resolve();
          }, 300);
        }, { once: true });
      });
      
      // Clean up
      URL.revokeObjectURL(audioUrl);
      console.log('Test completed successfully');
      
    } catch (error) {
      console.error('Test failed:', error);
      this.isSpeaking = false;
      this.switchAnimation('idle');
    }
  }

  // Test audio playback with a simple beep/tone
  async testAudioPlayback() {
    console.log('=== Testing Audio Playback ===');
    
    try {
      // Test 1: Web Audio API tone
      console.log('🧪 Test 1: Web Audio API tone...');
      const audioContext = new (window.AudioContext || window.webkitAudioContext)();
      console.log('Audio context created:', audioContext.state);
      
      if (audioContext.state === 'suspended') {
        await audioContext.resume();
        console.log('Audio context resumed');
      }
      
      // Test if we can play a simple tone
      const oscillator = audioContext.createOscillator();
      const gainNode = audioContext.createGain();
      
      oscillator.connect(gainNode);
      gainNode.connect(audioContext.destination);
      
      oscillator.frequency.setValueAtTime(440, audioContext.currentTime); // A4 note
      gainNode.gain.setValueAtTime(0.1, audioContext.currentTime);
      
      oscillator.start();
      oscillator.stop(audioContext.currentTime + 0.5); // Play for 0.5 seconds
      
      console.log('🎵 Audio test tone should be playing...');
      
      // Wait for the tone to finish
      await new Promise(resolve => setTimeout(resolve, 600));
      
      console.log('✅ Web Audio API test completed');
      
      // Test 2: Simple HTML5 Audio Element
      console.log('🧪 Test 2: HTML5 Audio Element...');
      const testAudioDataUrl = 'data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBSqBzvLZiTcIGGi67eefTQwLUKfj8LZjHAY4kdfyy3ksBSR3x/DdkEAKFF603OunVRQKRp/g8r5sIQUqgc7y2Yk3CBho';
      
      if (this.audioElement) {
        this.audioElement.src = testAudioDataUrl;
        
        try {
          await this.audioElement.play();
          console.log('✅ HTML5 Audio element test successful!');
          
          // Wait for it to finish
          await new Promise(resolve => {
            this.audioElement.addEventListener('ended', resolve, { once: true });
          });
          
        } catch (error) {
          console.error('❌ HTML5 Audio element test failed:', error);
          
          // If blocked, show info
          if (error.name === 'NotAllowedError') {
            console.log('🔒 Audio is blocked by browser autoplay policy');
          }
        }
      }
      
      // Test 3: Simple audio without lipsync
      console.log('🧪 Test 3: Simple audio without lipsync...');
      
      // Temporarily disable lipsync
      const originalLipsyncState = this.lipsyncEnabled;
      this.disableLipsync();
      
      try {
        await this.playAudioNewElement(testAudioDataUrl);
        console.log('✅ Simple audio test successful!');
      } catch (error) {
        console.error('❌ Simple audio test failed:', error);
      } finally {
        // Restore original lipsync state
        if (originalLipsyncState) {
          this.enableLipsync();
        }
      }
      
      // Test 4: Full audio pipeline test
      console.log('🧪 Test 4: Full audio pipeline...');
      try {
        await this.playAudioOnly(testAudioDataUrl);
        console.log('✅ Full audio pipeline test successful!');
      } catch (error) {
        console.error('❌ Full audio pipeline test failed:', error);
      }
      
    } catch (error) {
      console.error('❌ Audio test failed:', error);
    }
  }

  // Ultra-simple audio test - just play a sound
  async testSimpleAudio() {
    console.log('🧪 Testing ultra-simple audio...');
    
    try {
      // Create a completely new, simple audio element
      const simpleAudio = document.createElement('audio');
      simpleAudio.volume = 1.0;
      simpleAudio.muted = false;
      
      // Use a simple test sound
      const testAudioDataUrl = 'data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBSqBzvLZiTcIGGi67eefTQwLUKfj8LZjHAY4kdfyy3ksBSR3x/DdkEAKFF603OunVRQKRp/g8r5sIQUqgc7y2Yk3CBho';
      
      simpleAudio.src = testAudioDataUrl;
      
      // Add to DOM
      simpleAudio.style.display = 'none';
      document.body.appendChild(simpleAudio);
      
      console.log('🎵 Playing simple audio...');
      
      // Play it
      await simpleAudio.play();
      
      console.log('✅ Simple audio started successfully!');
      
      // Wait for it to finish
      await new Promise(resolve => {
        simpleAudio.addEventListener('ended', () => {
          console.log('🏁 Simple audio finished');
          document.body.removeChild(simpleAudio);
          resolve();
        });
      });
      
    } catch (error) {
      console.error('❌ Simple audio test failed:', error);
      if (error.name === 'NotAllowedError') {
        console.log('🔒 Audio blocked by browser - need user interaction');
      }
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
    // Clean up intervals
    if (this.lipsyncUpdateInterval) {
      clearInterval(this.lipsyncUpdateInterval);
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
      // Remove from DOM if it was added
      if (this.audioElement.parentNode) {
        this.audioElement.parentNode.removeChild(this.audioElement);
      }
    }
  }
  
  // Debug method to check avatar state
  debugAvatarState() {
    console.log('=== Avatar Debug Info ===');
    console.log('Is initialized:', this.isInitialized);
    console.log('Has audio element:', !!this.audioElement);
    if (this.audioElement) {
      console.log('Audio element details:', {
        volume: this.audioElement.volume,
        muted: this.audioElement.muted,
        readyState: this.audioElement.readyState,
        networkState: this.audioElement.networkState,
        paused: this.audioElement.paused,
        ended: this.audioElement.ended,
        currentTime: this.audioElement.currentTime,
        duration: this.audioElement.duration || 'unknown'
      });
    }
    console.log('Has API key:', !!this.options.elevenlabsApiKey);
    console.log('API key (first 10 chars):', this.options.elevenlabsApiKey ? this.options.elevenlabsApiKey.substring(0, 10) + '...' : 'None');
    console.log('Current animation:', this.currentAnimation);
    console.log('Is speaking:', this.isSpeaking);
    console.log('Has talking action:', !!this.talkingAction);
    console.log('Has idle action:', !!this.idleAction);
    console.log('Has lipsync:', !!this.rhubarbLipsync);
    console.log('Has Ready Player Me controller:', !!this.readyPlayerMeController);
    console.log('Morph targets found:', Object.keys(this.morphTargets).length);
    
    // Test system audio
    console.log('System audio test:');
    try {
      const audioContext = new (window.AudioContext || window.webkitAudioContext)();
      console.log('Audio context state:', audioContext.state);
      console.log('Audio context sample rate:', audioContext.sampleRate);
    } catch (error) {
      console.error('Audio context creation failed:', error);
    }
    
    console.log('========================');
  }
  
  // Debug methods to enable/disable lipsync
  enableLipsync() {
    this.lipsyncEnabled = true;
    if (!this.audioConnectedToLipsync) {
      this.connectAudioToLipsync();
    }
    console.log('✅ Lipsync enabled');
  }
  
  disableLipsync() {
    this.lipsyncEnabled = false;
    if (this.audioConnectedToLipsync && this.rhubarbLipsync.audioSource) {
      try {
        this.rhubarbLipsync.audioSource.disconnect();
        this.audioConnectedToLipsync = false;
        console.log('❌ Lipsync disabled and disconnected');
      } catch (e) {
        console.warn('Could not disconnect lipsync:', e);
      }
    }
  }
}

// Export for global use
window.AvatarManager = AvatarManager;
