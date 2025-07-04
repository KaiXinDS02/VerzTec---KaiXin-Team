// Ready Player Me Avatar Integration for VerzTec
// This file handles the specific requirements for Ready Player Me avatars

class ReadyPlayerMeAvatar {
  constructor(avatarManager) {
    this.avatarManager = avatarManager;
    this.morphTargetMappings = this.createMorphTargetMappings();
    this.expressionController = null;
    this.blinkController = null;
    this.idleAnimations = [];
  }
  
  createMorphTargetMappings() {
    // Comprehensive mapping for Ready Player Me morph targets to visemes
    return {
      'viseme_sil': {
        targets: ['mouthClose', 'mouthNeutral', 'neutral'],
        weight: 1.0
      },
      'viseme_PP': {
        targets: ['mouthPucker', 'mouthFunnel', 'mouthKiss'],
        weight: 0.9
      },
      'viseme_FF': {
        targets: ['mouthShrugLower', 'mouthLowerDown'],
        weight: 0.8
      },
      'viseme_TH': {
        targets: ['mouthShrugLower', 'mouthLowerDown'],
        weight: 0.7
      },
      'viseme_DD': {
        targets: ['mouthOpen', 'jawOpen', 'mouthAh'],
        weight: 0.8
      },
      'viseme_kk': {
        targets: ['mouthOpen', 'jawOpen'],
        weight: 0.6
      },
      'viseme_CH': {
        targets: ['mouthShrugUpper', 'mouthUpperUp'],
        weight: 0.7
      },
      'viseme_SS': {
        targets: ['mouthFrown', 'mouthSad'],
        weight: 0.8
      },
      'viseme_nn': {
        targets: ['mouthClose', 'mouthPress'],
        weight: 0.9
      },
      'viseme_RR': {
        targets: ['mouthPucker', 'mouthRoll'],
        weight: 0.8
      },
      'viseme_aa': {
        targets: ['mouthOpen', 'jawOpen', 'mouthAh'],
        weight: 1.0
      },
      'viseme_E': {
        targets: ['mouthSmile', 'mouthHappy', 'mouthEh'],
        weight: 0.9
      },
      'viseme_I': {
        targets: ['mouthSmile', 'mouthEe'],
        weight: 0.8
      },
      'viseme_O': {
        targets: ['mouthFunnel', 'mouthPucker', 'mouthOh'],
        weight: 0.9
      },
      'viseme_U': {
        targets: ['mouthPucker', 'mouthFunnel', 'mouthOoh'],
        weight: 0.9
      }
    };
  }
  
  findMorphTargets(avatar) {
    const foundTargets = {};
    
    avatar.traverse((child) => {
      if (child.isMesh && child.morphTargetDictionary) {
        console.log('Found mesh with morph targets:', child.name, Object.keys(child.morphTargetDictionary));
        
        // Log all available morph targets for debugging
        const allTargets = Object.keys(child.morphTargetDictionary);
        console.log('Available morph targets:', allTargets);
        
        // Look for viseme mappings with more flexible matching
        for (const [viseme, mapping] of Object.entries(this.morphTargetMappings)) {
          for (const targetName of mapping.targets) {
            const variations = [
              targetName,
              targetName.toLowerCase(),
              targetName.charAt(0).toUpperCase() + targetName.slice(1),
              targetName.charAt(0).toLowerCase() + targetName.slice(1),
              `blend_${targetName}`,
              `ARKit_${targetName}`,
              `${targetName}_L`,
              `${targetName}_R`,
              targetName.replace(/([A-Z])/g, '_$1').toLowerCase(),
              targetName.replace(/_/g, ''),
              // Ready Player Me specific variations
              `Wolf3D_${targetName}`,
              targetName.replace('mouth', 'Mouth'),
              targetName.replace('eye', 'Eye'),
              targetName.replace('jaw', 'Jaw')
            ];
            
            for (const variation of variations) {
              if (child.morphTargetDictionary[variation] !== undefined) {
                foundTargets[viseme] = {
                  mesh: child,
                  index: child.morphTargetDictionary[variation],
                  weight: mapping.weight,
                  targetName: variation
                };
                console.log(`✓ Mapped ${viseme} to ${variation} (index: ${child.morphTargetDictionary[variation]})`);
                break;
              }
            }
            
            if (foundTargets[viseme]) break;
          }
          
          // If no exact match found, try partial matching
          if (!foundTargets[viseme]) {
            const partialMatches = allTargets.filter(target => 
              mapping.targets.some(mappingTarget => 
                target.toLowerCase().includes(mappingTarget.toLowerCase()) ||
                mappingTarget.toLowerCase().includes(target.toLowerCase())
              )
            );
            
            if (partialMatches.length > 0) {
              const bestMatch = partialMatches[0];
              foundTargets[viseme] = {
                mesh: child,
                index: child.morphTargetDictionary[bestMatch],
                weight: mapping.weight * 0.8, // Reduce weight for partial matches
                targetName: bestMatch
              };
              console.log(`~ Partial match for ${viseme}: ${bestMatch}`);
            }
          }
        }
        
        // Look for eye blink controls with extended search
        const eyeBlinkTargets = [
          'eyeBlinkLeft', 'eyeBlinkRight', 'blink_L', 'blink_R',
          'EyeBlinkLeft', 'EyeBlinkRight', 'BlinkLeft', 'BlinkRight',
          'Wolf3D_Eye_Blink_Left', 'Wolf3D_Eye_Blink_Right',
          'eyeBlink_L', 'eyeBlink_R', 'eye_blink_left', 'eye_blink_right'
        ];
        
        for (const eyeTarget of eyeBlinkTargets) {
          if (child.morphTargetDictionary[eyeTarget] !== undefined) {
            foundTargets[eyeTarget] = {
              mesh: child,
              index: child.morphTargetDictionary[eyeTarget],
              weight: 1.0,
              targetName: eyeTarget
            };
            console.log(`✓ Found eye control: ${eyeTarget}`);
          }
        }
        
        // Look for additional expression controls
        const expressionTargets = [
          'mouthSmile', 'mouthFrown', 'eyeSquintLeft', 'eyeSquintRight',
          'browDownLeft', 'browDownRight', 'browInnerUp', 'cheekPuff',
          'MouthSmile', 'MouthFrown', 'EyeSquintLeft', 'EyeSquintRight'
        ];
        
        for (const exprTarget of expressionTargets) {
          if (child.morphTargetDictionary[exprTarget] !== undefined) {
            foundTargets[exprTarget] = {
              mesh: child,
              index: child.morphTargetDictionary[exprTarget],
              weight: 0.5,
              targetName: exprTarget
            };
          }
        }
      }
    });
    
    console.log('Total morph targets mapped:', Object.keys(foundTargets).length);
    console.log('Mapped targets:', Object.keys(foundTargets));
    
    return foundTargets;
  }
  
  setupExpressionController(avatar, morphTargets) {
    this.morphTargets = morphTargets;
    this.setupBlinking();
    this.setupIdleExpressions();
  }
  
  setupBlinking() {
    let blinkInterval;
    
    const blink = () => {
      const leftEye = this.morphTargets['eyeBlinkLeft'] || this.morphTargets['blink_L'];
      const rightEye = this.morphTargets['eyeBlinkRight'] || this.morphTargets['blink_R'];
      
      if (leftEye && rightEye) {
        // Quick blink animation
        const duration = 150;
        const startTime = Date.now();
        
        const animateBlink = () => {
          const elapsed = Date.now() - startTime;
          const progress = Math.min(elapsed / duration, 1);
          const blinkValue = Math.sin(progress * Math.PI);
          
          leftEye.mesh.morphTargetInfluences[leftEye.index] = blinkValue;
          rightEye.mesh.morphTargetInfluences[rightEye.index] = blinkValue;
          
          if (progress < 1) {
            requestAnimationFrame(animateBlink);
          }
        };
        
        animateBlink();
      }
      
      // Schedule next blink
      const nextBlink = 2000 + Math.random() * 4000; // 2-6 seconds
      blinkInterval = setTimeout(blink, nextBlink);
    };
    
    // Start blinking
    setTimeout(blink, 2000);
    
    this.blinkController = () => clearTimeout(blinkInterval);
  }
  
  setupIdleExpressions() {
    // Add subtle idle expressions to make the avatar more lifelike
    const expressions = ['mouthSmile', 'eyeSquintLeft', 'eyeSquintRight'];
    let currentExpression = null;
    
    const cycleExpressions = () => {
      if (currentExpression) {
        // Reset current expression
        const target = this.morphTargets[currentExpression];
        if (target) {
          this.lerpMorphTarget(target, 0, 0.02);
        }
      }
      
      // Randomly choose new expression or stay neutral
      if (Math.random() > 0.7) {
        const availableExpressions = expressions.filter(expr => this.morphTargets[expr]);
        if (availableExpressions.length > 0) {
          currentExpression = availableExpressions[Math.floor(Math.random() * availableExpressions.length)];
          const target = this.morphTargets[currentExpression];
          this.lerpMorphTarget(target, 0.2, 0.01);
        }
      } else {
        currentExpression = null;
      }
      
      setTimeout(cycleExpressions, 3000 + Math.random() * 5000);
    };
    
    setTimeout(cycleExpressions, 5000);
  }
  
  updateLipsync(viseme, intensity = 0.8) {
    // Reset all viseme targets to 0 first
    for (const [key, target] of Object.entries(this.morphTargets)) {
      if (key.startsWith('viseme_')) {
        if (target && target.mesh && target.index !== undefined) {
          this.lerpMorphTarget(target, 0, 0.3);
        }
      }
    }
    
    // Apply current viseme with smooth transition
    const target = this.morphTargets[viseme];
    if (target && target.mesh && target.index !== undefined) {
      const finalIntensity = Math.max(0, Math.min(1, intensity * target.weight));
      this.lerpMorphTarget(target, finalIntensity, 0.4);
      
      // Add slight jaw movement for more natural speech
      if (viseme === 'viseme_aa' || viseme === 'viseme_E' || viseme === 'viseme_O') {
        const jawTarget = this.morphTargets['jawOpen'] || this.morphTargets['JawOpen'];
        if (jawTarget) {
          this.lerpMorphTarget(jawTarget, finalIntensity * 0.3, 0.4);
        }
      }
    }
  }
  
  lerpMorphTarget(target, value, speed) {
    if (!target || !target.mesh || target.index === undefined) return;
    
    const current = target.mesh.morphTargetInfluences[target.index] || 0;
    target.mesh.morphTargetInfluences[target.index] = 
      THREE.MathUtils.lerp(current, value, speed);
  }
  
  setExpression(expression, intensity = 1.0, duration = 1000) {
    const target = this.morphTargets[expression];
    if (!target) return;
    
    const startValue = target.mesh.morphTargetInfluences[target.index] || 0;
    const startTime = Date.now();
    
    const animate = () => {
      const elapsed = Date.now() - startTime;
      const progress = Math.min(elapsed / duration, 1);
      const easeProgress = 1 - Math.pow(1 - progress, 3); // Ease out cubic
      
      const currentValue = startValue + (intensity - startValue) * easeProgress;
      target.mesh.morphTargetInfluences[target.index] = currentValue;
      
      if (progress < 1) {
        requestAnimationFrame(animate);
      }
    };
    
    animate();
  }
  
  destroy() {
    if (this.blinkController) {
      this.blinkController();
    }
  }
}

// Export for use in avatar manager
window.ReadyPlayerMeAvatar = ReadyPlayerMeAvatar;
