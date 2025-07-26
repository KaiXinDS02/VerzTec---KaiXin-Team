<?php
session_start();
include('connect.php'); 
include 'admin/auto_log_function.php';
require __DIR__ . '/vendor/autoload.php';

header('Content-Type: text/html; charset=utf-8');

// Determine user context
$role    = $_SESSION['role']    ?? '';
$dept = $_SESSION['department'] ?? 'Your Department';
$country = $_SESSION['country'] ?? 'Your Country';
$user_id = $_SESSION['user_id'] ?? 1;
?>





<!DOCTYPE html>
<html lang="en-US">
<head>
    <!-- 
    VOICE PREVIEW SYSTEM:
    - Uses local preview files from assets/audio/voice_previews/ instead of API calls
    - Preview files are pre-generated and named for each voice
    - API and voice ID are only used when voice is selected for actual speech
    - Preset avatar voices remain unchanged
    -->
    <!-- Meta setup -->
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Verztec</title>
    <link rel="icon" href="images/favicon.ico">
    <link rel="stylesheet" href="css/bootstrap.css">
    <link rel="stylesheet" href="css/font-awesome.css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="css/responsive.css">

    <style>
      /* Body setup for full viewport usage */
      html, body {
        height: 100%;
        margin: 0;
        padding: 0;
        overflow-x: hidden;
      }
      
      body {
        padding-top: 110px; /* Space for fixed header */
        background-color: #f2f3fa;
        display: flex;
        flex-direction: column;
      }

      /* Chat section takes full remaining viewport */
      .chat-section {
        height: calc(100vh - 110px); /* Full viewport minus header */
        padding: 20px 15px 20px 15px; /* Consistent padding on all sides */
        overflow: hidden;
        display: flex;
        flex-direction: column;
      }
      
      .chat-section .container-fluid {
        flex: 1;
        display: flex;
        flex-direction: column;
        max-width: none; /* Remove Bootstrap's max-width constraint */
        padding: 0; /* Remove default container padding */
      }
      
      .chat-section .row {
        flex: 1;
        margin: 0; /* Remove row margins */
        height: 100%;
      }
      
      .chat-section .col-lg-6 {
        padding: 0 10px; /* Reduce column padding */
      }

      /* Avatar container styles */
      .avatar-container {
        position: relative;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 8px;
        height: 100%;
        min-height: 500px;
        overflow: hidden;
        box-shadow: 0 8px 25px rgba(0,0,0,0.2);
      }
      
      .avatar-3d {
        width: 100%;
        height: 100%;
        position: relative;
      }
      
      .avatar-3d canvas {
        border-radius: 8px;
      }
      
      .avatar-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        pointer-events: none;
        z-index: 10;
      }
      
      .avatar-controls {
        position: absolute;
        top: 15px;
        right: 15px;
        display: flex;
        flex-direction: column;
        gap: 10px;
        pointer-events: auto;
      }
      
      .avatar-controls .btn {
        backdrop-filter: blur(10px);
        background: rgba(255, 255, 255, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.3);
        color: white;
        padding: 6px 12px;
        font-size: 0.85rem;
        border-radius: 6px;
        transition: all 0.3s ease;
      }
      
      .avatar-controls .btn:hover {
        background: rgba(255, 255, 255, 0.2);
        border-color: rgba(255, 255, 255, 0.3);
        transform: translateY(-2px);
      }
      
      /* Volume control styles */
      .volume-control {
        display: flex;
        align-items: center;
        gap: 8px;
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.3);
        border-radius: 6px;
        padding: 6px 10px;
        color: white;
        font-size: 0.8rem;
      }
      
      .volume-slider {
        width: 80px;
        height: 4px;
        background: rgba(255, 255, 255, 0.3);
        border-radius: 2px;
        outline: none;
        margin: 0;
        appearance: none;
      }
      
      .volume-slider::-webkit-slider-thumb {
        appearance: none;
        width: 14px;
        height: 14px;
        background: white;
        border-radius: 50%;
        cursor: pointer;
        box-shadow: 0 2px 4px rgba(0,0,0,0.3);
      }
      
      .volume-slider::-moz-range-thumb {
        width: 14px;
        height: 14px;
        background: white;
        border-radius: 50%;
        cursor: pointer;
        border: none;
        box-shadow: 0 2px 4px rgba(0,0,0,0.3);
      }
      
      /* Background color options */
      .background-controls {
        position: absolute;
        top: 15px;
        left: 15px;
        pointer-events: auto;
      }
      
      .background-dropdown {
        position: relative;
        display: inline-block;
      }
      
      .background-toggle {
        display: flex;
        align-items: center;
        gap: 8px;
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.3);
        border-radius: 6px;
        padding: 8px 12px;
        color: white;
        cursor: pointer;
        transition: all 0.3s ease;
        font-size: 0.8rem;
        min-width: 120px;
      }
      
      .background-toggle:hover {
        background: rgba(255, 255, 255, 0.2);
        border-color: rgba(255, 255, 255, 0.5);
      }
      
      .background-current-color {
        width: 20px;
        height: 20px;
        border-radius: 50%;
        border: 2px solid rgba(255, 255, 255, 0.5);
        flex-shrink: 0;
      }
      
      .background-menu {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(15px);
        border: 1px solid rgba(255, 255, 255, 0.3);
        border-radius: 6px;
        padding: 8px 0;
        margin-top: 4px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        display: none;
        z-index: 1000;
        min-width: 160px;
      }
      
      .background-menu.show {
        display: block;
      }
      
      .background-menu-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 12px;
        color: #333;
        cursor: pointer;
        transition: background-color 0.2s ease;
        font-size: 0.85rem;
      }
      
      .background-menu-item:hover {
        background: rgba(0,0,0,0.1);
      }
      
      .background-menu-item.active {
        background: rgba(0,0,0,0.1);
        font-weight: 600;
      }
      
      .background-menu-color {
        width: 16px;
        height: 16px;
        border-radius: 50%;
        border: 1px solid rgba(0,0,0,0.2);
        flex-shrink: 0;
        position: relative;
      }
      
      .background-menu-color .checkmark {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        color: white;
        font-size: 10px;
        font-weight: bold;
        opacity: 0;
        transition: opacity 0.2s ease;
        text-shadow: 0 1px 2px rgba(0,0,0,0.8);
      }
      
      .background-menu-item.active .checkmark {
        opacity: 1;
      }
      
      .avatar-controls .btn:hover {
        background: rgba(255, 255, 255, 0.2);
        border-color: rgba(255, 255, 255, 0.3);
        transform: translateY(-2px);
      }
      
      .avatar-controls .btn-group {
        margin-left: 10px;
      }
      
      .avatar-controls .speed-btn {
        font-size: 0.75rem;
        padding: 4px 8px;
        min-width: 35px;
      }
      
      .avatar-controls .speed-btn.active {
        background: rgba(255, 193, 7, 0.3);
        border-color: #ffc107;
        color: #ffc107;
      }
      
      .avatar-controls .speed-btn:hover {
        background: rgba(255, 193, 7, 0.2);
        border-color: #ffc107;
        color: #ffc107;
      }
      
      /* Avatar customization controls */
      .avatar-dropdown {
        position: relative;
        display: inline-block;
        margin-bottom: 10px;
      }
      
      .avatar-toggle {
        display: flex;
        align-items: center;
        gap: 8px;
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.3);
        border-radius: 6px;
        padding: 8px 12px;
        color: white;
        cursor: pointer;
        transition: all 0.3s ease;
        font-size: 0.8rem;
        min-width: 140px;
      }
      
      .avatar-toggle:hover {
        background: rgba(255, 255, 255, 0.2);
        border-color: rgba(255, 255, 255, 0.5);
      }
      
      .avatar-current-icon {
        width: 20px;
        height: 20px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.3);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
      }
      
      .avatar-menu {
        position: absolute;
        top: 100%;
        right: 0;
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(15px);
        border: 1px solid rgba(255, 255, 255, 0.3);
        border-radius: 8px;
        padding: 0;
        margin-top: 4px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        display: none;
        z-index: 1000;
        min-width: 280px;
        max-width: 350px;
      }
      
      .avatar-menu.show {
        display: block;
      }
      
      .avatar-menu-tabs {
        display: flex;
        background: rgba(0,0,0,0.05);
        border-radius: 8px 8px 0 0;
        padding: 4px;
      }
      
      .avatar-menu-tab {
        flex: 1;
        padding: 8px 12px;
        text-align: center;
        background: transparent;
        border: none;
        border-radius: 4px;
        color: #666;
        cursor: pointer;
        transition: all 0.2s ease;
        font-size: 0.8rem;
        font-weight: 500;
      }
      
      .avatar-menu-tab.active {
        background: white;
        color: #333;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
      }
      
      .avatar-menu-tab:hover:not(.active) {
        background: rgba(255,255,255,0.5);
        color: #333;
      }
      
      .avatar-menu-content {
        padding: 16px;
        max-height: 300px;
        overflow-y: auto;
      }
      
      .avatar-menu-section {
        display: none;
        margin-bottom: 16px;
      }
      
      .avatar-menu-section.active {
        display: block;
      }
      
      .avatar-menu-section:last-child {
        margin-bottom: 0;
      }
      
      .avatar-section-title {
        font-size: 0.9rem;
        font-weight: 600;
        color: #333;
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 6px;
      }
      
      .avatar-option-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(60px, 1fr));
        gap: 8px;
        margin-bottom: 12px;
      }
      
      .avatar-option-item {
        aspect-ratio: 1;
        border: 2px solid rgba(0,0,0,0.1);
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f8f9fa;
        position: relative;
        overflow: hidden;
      }
      
      .avatar-option-item:hover {
        border-color: #007bff;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.15);
      }
      
      .avatar-option-item.active {
        border-color: #007bff;
        background: rgba(0,123,255,0.1);
      }
      
      .avatar-option-item.active .checkmark {
        opacity: 1;
      }
      
      .avatar-option-item .checkmark {
        position: absolute;
        top: 4px;
        right: 4px;
        background: #007bff;
        color: white;
        width: 16px;
        height: 16px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 10px;
        opacity: 0;
        transition: opacity 0.2s ease;
      }
      
      .avatar-color-circle {
        width: 24px;
        height: 24px;
        border-radius: 50%;
        border: 2px solid rgba(0,0,0,0.1);
        flex-shrink: 0;
      }
      
      .avatar-preset-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 12px;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.2s ease;
        border: 1px solid rgba(0,0,0,0.1);
        margin-bottom: 8px;
      }
      
      .avatar-preset-item:hover {
        background: rgba(0,0,0,0.05);
        border-color: #007bff;
      }
      
      .avatar-preset-item.active {
        background: rgba(0,123,255,0.1);
        border-color: #007bff;
      }
      
      .avatar-preset-icon {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: linear-gradient(135deg, #007bff, #0056b3);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 14px;
      }
      
      .avatar-preset-info {
        flex: 1;
      }
      
      .avatar-preset-name {
        font-weight: 600;
        color: #333;
        margin-bottom: 2px;
      }
      
      .avatar-preset-desc {
        font-size: 0.8rem;
        color: #666;
      }
      
      .avatar-gender-buttons {
        display: flex;
        gap: 8px;
        margin-bottom: 12px;
      }
      
      .avatar-gender-btn {
        flex: 1;
        padding: 8px 12px;
        border: 1px solid rgba(0,0,0,0.2);
        border-radius: 6px;
        background: white;
        color: #666;
        cursor: pointer;
        transition: all 0.2s ease;
        font-size: 0.85rem;
        font-weight: 500;
      }
      
      .avatar-gender-btn:hover {
        border-color: #007bff;
        color: #007bff;
      }
      
      .avatar-gender-btn.active {
        background: #007bff;
        color: white;
        border-color: #007bff;
      }
      
      .avatar-info {
        position: absolute;
        bottom: 20px;
        left: 20px;
        color: white;
        text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        pointer-events: none;
      }
      
      .avatar-name {
        font-size: 1.5rem;
        font-weight: 600;
        margin-bottom: 5px;
        background: linear-gradient(45deg, #fff, #f0f0f0);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
      }
      
      .avatar-status {
        font-size: 0.9rem;
        opacity: 0.9;
        margin: 0;
      }
      
      /* Chat panel fills available space */
      .chat-panel {
        height: calc(100vh - 140px); /* Adjust height dynamically */
        max-height: calc(100vh - 140px); /* Prevent overflow beyond viewport */
        overflow: hidden; /* Prevent content overflow */
        display: flex;
        flex-direction: column;
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        transition: all 0.3s ease;
      }
      
      /* Chat panel expanded when avatar is hidden */
      .chat-panel.expanded {
        height: calc(100vh - 140px);
        max-height: calc(100vh - 140px);
      }
      
      /* Avatar container transition */
      .avatar-container {
        transition: all 0.3s ease;
      }
      
      .avatar-container.hidden {
        transform: translateX(-100%);
        opacity: 0;
      }
      
      /* Chat section adjustments when avatar is hidden */
      .chat-section.avatar-hidden .col-lg-6:first-child {
        width: 0;
        padding: 0;
        overflow: hidden;
        transition: all 0.3s ease;
      }
      
      .chat-section.avatar-hidden .col-lg-6:last-child {
        width: 100%;
        max-width: 100%;
        flex: 0 0 100%;
        transition: all 0.3s ease;
      }
      
      /* Toggle avatar button in chat header */
      .chat-header-controls {
        display: flex;
        align-items: center;
        gap: 10px;
      }
      
      .chat-header-controls .btn-sm {
        padding: 4px 8px;
        font-size: 0.8rem;
        border-radius: 4px;
        transition: all 0.3s ease;
      }
      
      .chat-header-controls .btn-outline-primary {
        border-color: #333;
        color: #fff;
        background-color: #333;
      }
      
      .chat-header-controls .btn-outline-primary:hover {
        background-color: #ffc107;
        border-color: #ffc107;
        color: #333;
      }
      
      .chat-header-controls .btn-outline-primary i {
        color: #fff !important;
      }
      
      .chat-header-controls .btn-outline-primary:hover i {
        color: #333 !important;
      }
      
      .chat-header {
        background: #fff;
        border-top-left-radius: 8px;
        border-top-right-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        padding: 1rem;
        flex-shrink: 0; /* Don't shrink the header */
      }
      
      .chat-body {
        flex: 1; /* Take up remaining space */
        overflow-y: auto; /* Enable vertical scrolling */
        overflow-x: hidden; /* Prevent horizontal scrolling */
        padding: 1rem;
        background: #fafbfc;
        border-left: 1px solid #dee2e6;
        border-right: 1px solid #dee2e6;
        box-sizing: border-box;
      }
      
      /* Message bubble styles */
      .chat-body .bot-initial {
        max-width: 80%;
        display: inline-block;
        background: #fff;
        border: 1px solid #ffc107;
        border-radius: 20px;
        padding: 0.75rem 1rem;
        margin-bottom: 1rem;
        color: #333;
      }
      
      .chat-body .user-bubble {
        max-width: 80%;
        background: #ffc107;
        color: #333;
        border-radius: 20px;
        padding: 0.75rem 1rem;
        margin-bottom: 1rem;
        margin-left: auto;
        display: block;
        text-align: left;
        word-wrap: break-word;
      }
      
      .chat-body .bot-bubble {
        max-width: 80%;
        display: block;
        background: #fff;
        border: 1px solid #ffc107;
        border-radius: 20px;
        padding: 0.75rem 1rem;
        margin-bottom: 1rem;
        margin-right: auto;
        color: #333;
        word-wrap: break-word;
      }
      
      /* Fix text overflow issues */
      .chat-body .bot-bubble,
      .chat-body .user-bubble {
        word-wrap: break-word;
        overflow-wrap: break-word;
        hyphens: auto;
      }

      /* Chat input group */
      .chat-input-group {
        display: flex;
        align-items: center;
        padding: 1rem;
        background: #fff;
        border: 1px solid #dee2e6;
        border-bottom-left-radius: 8px;
        border-bottom-right-radius: 8px;
        flex-shrink: 0; /* Don't shrink the input area */
      }
      
      .chat-input-group input.form-control {
        border: 1px solid #dee2e6;
        box-shadow: none;
        border-radius: 24px;
        padding: 0.75rem 1rem;
        flex: 1;
        transition: all 0.3s ease;
      }
      
      .chat-input-group input.form-control:disabled {
        background-color: #f8f9fa;
        border-color: #dee2e6;
        color: #6c757d;
        opacity: 0.6;
        cursor: not-allowed;
      }
      
      .chat-input-group input.form-control:disabled::placeholder {
        color: #adb5bd;
      }
      
      .chat-input-group .btn-icon {
        border: none;
        background: transparent;
        font-size: 1.2rem;
        color: #333;
        margin-left: 0.5rem;
        padding: 0.5rem;
        border-radius: 50%;
        transition: all 0.3s ease;
        cursor: pointer;
      }
      
      .chat-input-group .btn-icon:hover {
        background-color: #ffc107;
        color: #fff;
        transform: scale(1.1);
        border-radius: 50%; /* Make icons circular */
      }
      
      .chat-input-group .btn-icon:active {
        transform: scale(0.95);
        background-color: #e0a800;
      }
      
      .chat-input-group .btn-icon:focus {
        box-shadow: none;
        outline: none;
      }
      
      .chat-input-group .btn-icon:disabled,
      .chat-input-group .btn-icon[style*="pointer-events: none"] {
        opacity: 0.5;
        cursor: not-allowed;
        transform: none;
      }
      
      .chat-input-group .btn-icon:disabled:hover,
      .chat-input-group .btn-icon[style*="pointer-events: none"]:hover {
        background: transparent;
        color: #6c757d;
        transform: none;
      }

      /* Voice recording styles */
      .chat-input-group .btn-icon.recording {
        background-color: #dc3545 !important;
        color: white !important;
      }
      
      .chat-input-group .btn-icon.recording:hover {
        background-color: #c82333 !important;
        color: white !important;
      }
      
      /* Pulse animation for recording state */
      @keyframes pulse {
        0% {
          box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7);
        }
        70% {
          box-shadow: 0 0 0 10px rgba(220, 53, 69, 0);
        }
        100% {
          box-shadow: 0 0 0 0 rgba(220, 53, 69, 0);
        }
      }

      /* Maintain consistent spacing between messages */
      .chat-body .bot-bubble,
      .chat-body .user-bubble {
        margin-bottom: 1rem; /* Consistent spacing */
      }
      
      /* Thinking animation styles */
      .thinking-animation {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        margin-left: 8px;
      }
      
      .thinking-dot {
        display: inline-block;
        width: 6px;
        height: 6px;
        background-color: #ffc107;
        border-radius: 50%;
        opacity: 0.4;
        transition: all 0.2s ease;
        font-size: 6px;
        line-height: 1;
        box-shadow: 0 0 3px rgba(255, 193, 7, 0.3);
      }
      
      .thinking-dot.active {
        opacity: 1;
        transform: scale(1.3);
        background-color: #e0a800;
        box-shadow: 0 0 8px rgba(255, 193, 7, 0.6);
      }
      
      @keyframes thinking-pulse {
        0% {
          opacity: 0.4;
          transform: scale(1);
        }
        50% {
          opacity: 1;
          transform: scale(1.3);
        }
        100% {
          opacity: 0.4;
          transform: scale(1);
        }
      }
      
      /* Responsive adjustments */
      @media (max-width: 768px) {
        .avatar-controls {
          top: 10px;
          right: 10px;
          gap: 8px;
        }
        
        .background-controls {
          top: 10px;
          left: 10px;
          gap: 8px;
        }
        
        .background-option {
          width: 25px;
          height: 25px;
        }
        
        .volume-control {
          padding: 5px 8px;
          font-size: 0.75rem;
        }
        
        .volume-slider {
          width: 60px;
        }
        
        .avatar-controls .btn {
          padding: 4px 8px;
          font-size: 0.75rem;
        }
      }
      
      /* Ready Player Me Modal Styles */
      .rpm-modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.8);
        z-index: 10000;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        box-sizing: border-box;
      }
      
      .rpm-modal-content {
        background: white;
        border-radius: 12px;
        width: 100%;
        max-width: 1200px;
        max-height: 90vh;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
      }
      
      .rpm-modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px 24px;
        border-bottom: 1px solid #e0e0e0;
        background: #f8f9fa;
      }
      
      .rpm-modal-header h3 {
        margin: 0;
        color: #333;
        font-size: 1.2rem;
        font-weight: 600;
      }
      
      .rpm-close-btn {
        background: none;
        border: none;
        font-size: 1.5rem;
        color: #666;
        cursor: pointer;
        padding: 5px;
        border-radius: 50%;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
      }
      
      .rpm-close-btn:hover {
        background: #e0e0e0;
        color: #333;
      }
      
      .rpm-modal-body {
        flex: 1;
        overflow: hidden;
      }
      
      .rpm-frame {
        width: 100%;
        height: 700px;
        border: none;
        display: block;
      }
      
      @media (max-width: 768px) {
        .rpm-modal {
          padding: 10px;
        }
        
        .rpm-modal-content {
          max-height: 95vh;
        }
        
        .rpm-modal-header {
          padding: 15px 20px;
        }
        
        .rpm-modal-header h3 {
          font-size: 1.1rem;
        }
        
        .rpm-frame {
          height: 600px;
        }
      }
      
      /* Avatar Selection Circular Buttons */
      .avatar-selection-circular {
        position: absolute;
        bottom: 20px;
        left: 50%;
        transform: translateX(-50%);
        display: flex;
        gap: 15px;
        z-index: 1000;
        pointer-events: auto;
      }
      
      .avatar-circle-btn {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        border: 2px solid rgba(255, 255, 255, 0.8);
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(10px);
        color: white;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        position: relative;
        pointer-events: auto;
        z-index: 1001;
        outline: none;
        user-select: none;
        -webkit-user-select: none;
        -moz-user-select: none;
        -ms-user-select: none;
      }
      
      .avatar-circle-btn:focus {
        outline: 2px solid #007bff;
        outline-offset: 2px;
      }
      
      .avatar-circle-btn:hover {
        background: rgba(255, 255, 255, 0.2);
        border-color: rgba(255, 255, 255, 1);
        transform: scale(1.1);
        box-shadow: 0 4px 15px rgba(0,0,0,0.3);
      }
      
      .avatar-circle-btn.active {
        background: rgba(0, 123, 255, 0.3);
        border-color: #007bff;
        color: #007bff;
        box-shadow: 0 0 15px rgba(0, 123, 255, 0.4);
      }
      
      .avatar-circle-btn.active:hover {
        background: rgba(0, 123, 255, 0.4);
        transform: scale(1.1);
      }
      
      /* Special styling for customize button */
      .avatar-circle-btn#customize-avatar-btn {
        background: rgba(74, 144, 226, 0.2);
        border-color: #4a90e2;
      }
      
      .avatar-circle-btn#customize-avatar-btn:hover {
        background: rgba(74, 144, 226, 0.3);
        border-color: #4a90e2;
        transform: scale(1.1);
      }
      
      /* Avatar customization selection modals */
      .avatar-selection-modal {
        display: none;
        position: fixed;
        z-index: 10001;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.5);
        backdrop-filter: blur(5px);
      }
      
      .avatar-selection-modal.show {
        display: block;
      }
      
      .avatar-selection-content {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: white;
        padding: 30px;
        border-radius: 15px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        max-width: 500px;
        width: 90%;
        text-align: center;
      }
      
      .avatar-selection-content h3 {
        margin-bottom: 20px;
        color: #333;
        font-size: 24px;
        font-weight: 600;
      }
      
      .avatar-selection-content p {
        margin-bottom: 25px;
        color: #666;
        font-size: 16px;
        line-height: 1.5;
      }
      
      .gender-selection-buttons {
        display: flex;
        gap: 20px;
        justify-content: center;
        margin-bottom: 20px;
      }
      
      .gender-btn {
        padding: 15px 30px;
        border: 2px solid #ddd;
        border-radius: 10px;
        background: white;
        color: #333;
        font-size: 16px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 8px;
        min-width: 120px;
      }
      
      .gender-btn:hover {
        border-color: #007bff;
        background: #f8f9ff;
        transform: translateY(-2px);
      }
      
      .gender-btn.selected {
        border-color: #007bff;
        background: #007bff;
        color: white;
      }
      
      .gender-btn i {
        font-size: 24px;
        margin-bottom: 5px;
      }
      
      .voice-selection-container {
        margin-bottom: 25px;
      }
      
      .voice-options {
        display: flex;
        flex-direction: column;
        gap: 15px;
        margin-bottom: 20px;
      }
      
      .voice-option {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 15px;
        border: 2px solid #ddd;
        border-radius: 10px;
        background: white;
        cursor: pointer;
        transition: all 0.3s ease;
      }
      
      .voice-option:hover {
        border-color: #007bff;
        background: #f8f9ff;
      }
      
      .voice-option.selected {
        border-color: #007bff;
        background: #007bff;
        color: white;
      }
      
      .voice-option-info {
        display: flex;
        align-items: center;
        gap: 10px;
      }
      
      .voice-option-info i {
        font-size: 20px;
        width: 25px;
        text-align: center;
      }
      
      .voice-option-name {
        font-weight: 500;
        font-size: 16px;
      }
      
      .voice-preview-btn {
        padding: 8px 15px;
        border: 1px solid #ddd;
        border-radius: 6px;
        background: white;
        color: #333;
        font-size: 14px;
        cursor: pointer;
        transition: all 0.3s ease;
      }
      
      .voice-preview-btn:hover {
        background: #f5f5f5;
        border-color: #bbb;
      }
      
      .voice-option.selected .voice-preview-btn {
        background: rgba(255,255,255,0.2);
        border-color: rgba(255,255,255,0.3);
        color: white;
      }
      
      .voice-option.selected .voice-preview-btn:hover {
        background: rgba(255,255,255,0.3);
        border-color: rgba(255,255,255,0.5);
      }
      
      .selection-modal-buttons {
        display: flex;
        gap: 15px;
        justify-content: center;
        margin-top: 20px;
      }
      
      .selection-btn {
        padding: 12px 25px;
        border: none;
        border-radius: 8px;
        font-size: 16px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s ease;
        min-width: 100px;
      }
      
      .selection-btn.primary {
        background: #007bff;
        color: white;
      }
      
      .selection-btn.primary:hover {
        background: #0056b3;
        transform: translateY(-1px);
      }
      
      .selection-btn.secondary {
        background: #6c757d;
        color: white;
      }
      
      .selection-btn.secondary:hover {
        background: #545b62;
        transform: translateY(-1px);
      }
      
      .selection-btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
      }
      
      .preview-status {
        font-size: 14px;
        color: #666;
        margin-top: 10px;
        min-height: 20px;
      }
      
      @media (max-width: 576px) {
        .avatar-selection-content {
          padding: 20px;
          margin: 20px;
        }
        
        .gender-selection-buttons {
          flex-direction: column;
          gap: 15px;
        }
        
        .gender-btn {
          flex-direction: row;
          min-width: auto;
        }
        
        .voice-options {
          gap: 10px;
        }
        
        .voice-option {
          padding: 12px;
        }
        
        .selection-modal-buttons {
          flex-direction: column;
        }
      }

      /* Avatar customization selection modals */
      .avatar-selection-modal {
        display: none;
        position: fixed;
        z-index: 10001;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.5);
        backdrop-filter: blur(5px);
      }
      
      .avatar-selection-modal.show {
        display: block;
      }
      
      .avatar-selection-content {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: white;
        padding: 30px;
        border-radius: 15px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        max-width: 500px;
        width: 90%;
        text-align: center;
      }
      
      .avatar-selection-content h3 {
        margin-bottom: 20px;
        color: #333;
        font-size: 24px;
        font-weight: 600;
      }
      
      .avatar-selection-content p {
        margin-bottom: 25px;
        color: #666;
        font-size: 16px;
        line-height: 1.5;
      }
      
      .gender-selection-buttons {
        display: flex;
        gap: 20px;
        justify-content: center;
        margin-bottom: 20px;
      }
      
      .gender-btn {
        padding: 15px 30px;
        border: 2px solid #ddd;
        border-radius: 10px;
        background: white;
        color: #333;
        font-size: 16px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 8px;
        min-width: 120px;
      }
      
      .gender-btn:hover {
        border-color: #007bff;
        background: #f8f9ff;
        transform: translateY(-2px);
      }
      
      .gender-btn.selected {
        border-color: #007bff;
        background: #007bff;
        color: white;
      }
      
      .gender-btn i {
        font-size: 24px;
        margin-bottom: 5px;
      }
      
      .voice-selection-container {
        margin-bottom: 25px;
      }
      
      .voice-options {
        display: flex;
        flex-direction: column;
        gap: 15px;
        margin-bottom: 20px;
      }
      
      .voice-option {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 15px;
        border: 2px solid #ddd;
        border-radius: 10px;
        background: white;
        cursor: pointer;
        transition: all 0.3s ease;
      }
      
      .voice-option:hover {
        border-color: #007bff;
        background: #f8f9ff;
      }
      
      .voice-option.selected {
        border-color: #007bff;
        background: #007bff;
        color: white;
      }
      
      .voice-option-info {
        display: flex;
        align-items: center;
        gap: 10px;
      }
      
      .voice-option-info i {
        font-size: 20px;
        width: 25px;
        text-align: center;
      }
      
      .voice-option-name {
        font-weight: 500;
        font-size: 16px;
      }
      
      .voice-preview-btn {
        padding: 8px 15px;
        border: 1px solid #ddd;
        border-radius: 6px;
        background: white;
        color: #333;
        font-size: 14px;
        cursor: pointer;
        transition: all 0.3s ease;
      }
      
      .voice-preview-btn:hover {
        background: #f5f5f5;
        border-color: #bbb;
      }
      
      .voice-option.selected .voice-preview-btn {
        background: rgba(255,255,255,0.2);
        border-color: rgba(255,255,255,0.3);
        color: white;
      }
      
      .voice-option.selected .voice-preview-btn:hover {
        background: rgba(255,255,255,0.3);
        border-color: rgba(255,255,255,0.5);
      }
      
      .selection-modal-buttons {
        display: flex;
        gap: 15px;
        justify-content: center;
        margin-top: 20px;
      }
      
      .selection-btn {
        padding: 12px 25px;
        border: none;
        border-radius: 8px;
        font-size: 16px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s ease;
        min-width: 100px;
      }
      
      .selection-btn.primary {
        background: #007bff;
        color: white;
      }
      
      .selection-btn.primary:hover {
        background: #0056b3;
        transform: translateY(-1px);
      }
      
      .selection-btn.secondary {
        background: #6c757d;
        color: white;
      }
      
      .selection-btn.secondary:hover {
        background: #545b62;
        transform: translateY(-1px);
      }
      
      .selection-btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
      }
      
      .preview-status {
        font-size: 14px;
        color: #666;
        margin-top: 10px;
        min-height: 20px;
      }
      
      @media (max-width: 576px) {
        .avatar-selection-content {
          padding: 20px;
          margin: 20px;
        }
        
        .gender-selection-buttons {
          flex-direction: column;
          gap: 15px;
        }
        
        .gender-btn {
          flex-direction: row;
          min-width: auto;
        }
        
        .voice-options {
          gap: 10px;
        }
        
        .voice-option {
          padding: 12px;
        }
        
        .selection-modal-buttons {
          flex-direction: column;
        }
      }
    </style>
</head>
<body>

  <!-- header (unchanged) -->
  <header class="header-area" style="position: fixed; top:0; left:0; width:100%; z-index:999; background:white;">
    <div class="container-fluid">
      <div class="row align-items-center">
        <div class="col-xl-3 col-md-4 col-6">
          <a href="home.php" class="page-logo-wp"><img src="images/logo.png" alt=""></a>
        </div>
        <div class="col-xl-6 col-md-5 order-3 order-md-2 d-flex justify-content-center justify-content-md-start">
          <div class="page-menu-wp">
            <ul>
              <li><a href="home.php">Home</a></li>
              <li class="active"><a href="chatbot.php">Chatbot</a></li>
              <li><a href="files.php">Files</a></li>
              <?php if ($_SESSION['role'] !== 'USER'): ?>
                <li><a href="admin/users.php">Admin</a></li>
              <?php endif; ?>
            </ul>
          </div>
        </div>
        <div class="col-md-3 col-6 d-flex justify-content-end order-2 order-md-3">
          <div class="page-user-icon profile">
            <button><img src="images/Profile-Icon.svg" alt=""></button>
            <div class="menu">
              <ul>
                <li><a href="#"><i class="fa-regular fa-user"></i> Profile</a></li>
                <li><a href="#"><i class="fa-regular fa-message-smile"></i> Inbox</a></li>
                <li><a href="#"><i class="fa-regular fa-gear"></i> Settings</a></li>
                <li><a href="#"><i class="fa-regular fa-square-question"></i> Help</a></li>
                <li><a href="login.php"><i class="fa-regular fa-right-from-bracket"></i> Sign Out</a></li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>
  </header>

  <!-- Chatbot Interface -->
  <section class="chat-section py-4" id="chat-section">
    <div class="container-fluid">
      <div class="row">

        <!-- Left half for 3D avatar -->
        <div class="col-lg-6 mb-4 mb-lg-0" id="avatar-column">
          <div class="avatar-container h-100">
            <div id="avatar-3d" class="avatar-3d"></div>
            <div class="avatar-overlay">
              <div class="background-controls">
                <div class="background-dropdown">
                  <div class="background-toggle" id="background-toggle">
                    <div class="background-current-color" 
                         style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    </div>
                    <span>Background</span>
                    <i class="fa fa-chevron-down" style="margin-left: auto; font-size: 0.7rem;"></i>
                  </div>
                  <div class="background-menu" id="background-menu">
                    <div class="background-menu-item active" 
                         data-bg="linear-gradient(135deg, #667eea 0%, #764ba2 100%)"
                         data-name="Purple Gradient">
                      <div class="background-menu-color" 
                           style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                        <i class="fa fa-check checkmark"></i>
                      </div>
                      <span>Purple Gradient</span>
                    </div>
                    <div class="background-menu-item" 
                         data-bg="linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%)"
                         data-name="Red Gradient">
                      <div class="background-menu-color" 
                           style="background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);">
                        <i class="fa fa-check checkmark"></i>
                      </div>
                      <span>Red Gradient</span>
                    </div>
                    <div class="background-menu-item" 
                         data-bg="linear-gradient(135deg, #0abde3 0%, #006ba6 100%)"
                         data-name="Blue Gradient">
                      <div class="background-menu-color" 
                           style="background: linear-gradient(135deg, #0abde3 0%, #006ba6 100%);">
                        <i class="fa fa-check checkmark"></i>
                      </div>
                      <span>Blue Gradient</span>
                    </div>
                    <div class="background-menu-item" 
                         data-bg="linear-gradient(135deg, #10ac84 0%, #1dd1a1 100%)"
                         data-name="Green Gradient">
                      <div class="background-menu-color" 
                           style="background: linear-gradient(135deg, #10ac84 0%, #1dd1a1 100%);">
                        <i class="fa fa-check checkmark"></i>
                      </div>
                      <span>Green Gradient</span>
                    </div>
                    <div class="background-menu-item" 
                         data-bg="linear-gradient(135deg, #feca57 0%, #ff9ff3 100%)"
                         data-name="Yellow Pink">
                      <div class="background-menu-color" 
                           style="background: linear-gradient(135deg, #feca57 0%, #ff9ff3 100%);">
                        <i class="fa fa-check checkmark"></i>
                      </div>
                      <span>Yellow Pink</span>
                    </div>
                    <div class="background-menu-item" 
                         data-bg="linear-gradient(135deg, #2c2c54 0%, #40407a 100%)"
                         data-name="Dark Purple">
                      <div class="background-menu-color" 
                           style="background: linear-gradient(135deg, #2c2c54 0%, #40407a 100%);">
                        <i class="fa fa-check checkmark"></i>
                      </div>
                      <span>Dark Purple</span>
                    </div>
                  </div>
                </div>
              </div>
              <div class="avatar-controls">
                <div class="volume-control">
                  <i class="fa fa-volume-down" style="font-size: 0.8rem;"></i>
                  <input type="range" id="avatar-volume-slider" class="volume-slider" 
                         min="0" max="100" value="100" step="1">
                  <i class="fa fa-volume-up" style="font-size: 0.8rem;"></i>
                  <span id="avatar-volume-display" style="font-size: 0.8rem; min-width: 25px;">100%</span>
                </div>
                <button id="toggle-voice" class="btn btn-sm btn-outline-light">
                  <i class="fa fa-volume-up"></i> Voice On
                </button>
                <button id="toggle-avatar" class="btn btn-sm btn-outline-light">
                  <i class="fa fa-robot"></i> Avatar On
                </button>
                <div class="btn-group" role="group">
                  <button id="speed-1x" class="btn btn-sm btn-outline-light speed-btn active" data-speed="1">1x</button>
                  <button id="speed-1.5x" class="btn btn-sm btn-outline-light speed-btn" data-speed="1.5">1.5x</button>
                  <button id="speed-2x" class="btn btn-sm btn-outline-light speed-btn" data-speed="2">2x</button>
                  <button id="speed-3x" class="btn btn-sm btn-outline-light speed-btn" data-speed="3">3x</button>
                </div>
              </div>
              <div class="avatar-info">
                <h3 class="avatar-name">VerzTec AI Assistant</h3>
                <p class="avatar-status">Ready to help</p>
              </div>
            </div>
            
            <!-- Avatar Selection Circular Buttons -->
            <div class="avatar-selection-circular">
              <button class="avatar-circle-btn active" data-avatar="female" title="Female Avatar">
                <i class="fa fa-female"></i>
              </button>
              <button class="avatar-circle-btn" data-avatar="male" title="Male Avatar">
                <i class="fa fa-male"></i>
              </button>
              <button class="avatar-circle-btn" id="customize-avatar-btn" title="Customize Avatar">
                <i class="fa fa-plus"></i>
              </button>
            </div>
          </div>
        </div>

        <!-- Right half for chat -->
        <div class="col-lg-6 d-flex" id="chat-column">
          <div class="chat-panel w-100 shadow-sm rounded">

            <div class="chat-header">
              <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fa fa-robot me-2"></i>VerzTec AI Assistant</h5>
                <div class="chat-header-controls">
    <button id="history-button" class="btn btn-outline-primary ms-2" style="
      display: inline-flex;
      align-items: center;
      gap: 6px;
      font-size: 13px;
     font-size: 14px; padding: 6px 14px;">
      <i class="fa fa-history"></i> History
    </button>
                  <button id="show-avatar" class="btn btn-sm btn-outline-primary" style="display: none;">
                    <i class="fa fa-robot"></i> Show Avatar
                  </button>
                  <button id="chat-toggle-voice" class="btn btn-sm btn-outline-primary" style="display: none;">
                    <i class="fa fa-volume-up"></i> Voice On
                  </button>
                  <div class="btn-group" role="group" id="chat-speed-controls" style="display: none;">
                    <button class="btn btn-sm btn-outline-primary speed-btn active" data-speed="1">1x</button>
                    <button class="btn btn-sm btn-outline-primary speed-btn" data-speed="1.5">1.5x</button>
                    <button class="btn btn-sm btn-outline-primary speed-btn" data-speed="2">2x</button>
                    <button class="btn btn-sm btn-outline-primary speed-btn" data-speed="3">3x</button>
                  </div>
                </div>
              </div>
            </div>

            <div id="chat-container" class="chat-body">
              <div class="bot-initial">
                <strong>VerzTec Assistant:</strong> Hello! I'm here to help you today. 😊
              </div>
            </div>

            <div class="chat-input-group">
              <input type="text"
                     id="user-input"
                     class="form-control"
                     placeholder="Ask anything..."
                     autocomplete="off"
                     autocorrect="off"
                     autocapitalize="off"
                     spellcheck="false"
                     onkeypress="handleKeyPress(event)">
              <button class="btn-icon" type="button" onclick="if (!isChatbotBusy) sendMessage()">
                <i class="fa fa-paper-plane"></i>
              </button>
              <button class="btn-icon" type="button" id="voice-record-btn" onclick="toggleVoiceRecording()">
                <i class="fa fa-microphone"></i>
              </button>
              <button class="btn-icon" type="button">
                <i class="fa fa-plus"></i>
              </button>
            </div>

          </div>
        </div>

      </div>
    </div>
  </section>

  <!-- Gender Selection Modal -->
  <div id="gender-selection-modal" class="avatar-selection-modal">
    <div class="avatar-selection-content">
      <h3>🎭 Character Gender</h3>
      <p>Please select the gender of your customized avatar to ensure proper animations and voice matching.</p>
      
      <div class="gender-selection-buttons">
        <button class="gender-btn" data-gender="female" onclick="selectGender('female')">
          <i class="fa fa-female"></i>
          <span>Feminine</span>
        </button>
        <button class="gender-btn" data-gender="male" onclick="selectGender('male')">
          <i class="fa fa-male"></i>
          <span>Masculine</span>
        </button>
      </div>
      
      <div class="selection-modal-buttons">
        <button class="selection-btn primary" id="confirm-gender-btn" onclick="confirmGender()" disabled>
          Continue
        </button>
        <button class="selection-btn secondary" onclick="cancelAvatarCustomization()">
          Cancel
        </button>
      </div>
    </div>
  </div>

  <!-- Voice Selection Modal -->
  <div id="voice-selection-modal" class="avatar-selection-modal">
    <div class="avatar-selection-content">
      <h3>🎙️ Voice Selection</h3>
      <p>Choose a voice for your avatar and preview how it sounds.</p>
      
      <div class="voice-selection-container">
        <div class="voice-options" id="voice-options-container">
          <!-- Voice options will be populated by JavaScript -->
        </div>
        
        <div class="preview-status" id="voice-preview-status">
          Click "Preview" to hear how each voice sounds
        </div>
      </div>
      
      <div class="selection-modal-buttons">
        <button class="selection-btn primary" id="confirm-voice-btn" onclick="confirmVoiceSelection()" disabled>
          Apply Settings
        </button>
        <button class="selection-btn secondary" onclick="goBackToGenderSelection()">
          Back
        </button>
      </div>
    </div>
  </div>

  <!-- NOTIFICATION AND MODAL -->
    <div class="modal fade" id="announcementModal" tabindex="-1" aria-labelledby="announcementModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered" style="max-width: 600px;">
        <div class="modal-content" style="border-radius: 12px; overflow: hidden; border: none;">
          <div class="modal-header" style="background-color:#81869E; color:#fff; border-radius: 12px 12px 0 0;">
            <h5 class="modal-title" id="announcementModalLabel" style="
              font-family: 'Gudea', sans-serif;
              font-weight: normal;
              white-space: normal;
              word-wrap: break-word;
              overflow-wrap: break-word;
            ">Announcement</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>

          <div class="modal-body" style="
            font-family: 'Open Sans', sans-serif;
            color:#000;
            font-size:1rem;
            padding: 1.5rem 1.75rem;
            word-wrap: break-word;
            overflow-wrap: break-word;
            white-space: normal;
          ">
            <h5 id="modalTitle" style="
              margin-bottom: 1rem;
              word-wrap: break-word;
              overflow-wrap: break-word;
              white-space: pre-wrap;
            "></h5>

            <p id="modalContent" style="
              margin: 0;
              padding: 0;
              text-align: left;
              word-wrap: break-word;
              overflow-wrap: break-word;
              white-space: pre-wrap;
            "></p>

            <hr>
            <p><strong>Target Audience:</strong> <span id="modalAudience"></span></p>
            <p><strong>Priority:</strong> <span id="modalPriority"></span></p>
            <p><strong>Posted:</strong> <span id="modalTimestamp"></span></p>
          </div>
        </div>
      </div>
    </div>

  <!-- All your existing scripts (unchanged) -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/loaders/GLTFLoader.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/loaders/FBXLoader.js"></script>
  <script src="js/rhubarb-lipsync.js"></script>
  <script src="js/ready-player-me-avatar.js"></script>
  <script src="js/avatar-manager.js"></script>
  <script src="js\notification.js"></script> <!-- script for handling announcements -->
  <script>
    // Global variables
    let avatarManager = null;
    let isVoiceEnabled = true;
    let isAvatarEnabled = true;
    let currentSpeed = 1.0; // Speed multiplier for text and speech
    let isChatbotBusy = false; // Track if chatbot is currently processing/talking
    
    // Avatar system configuration - HARDCODED ANIMATION MAPPINGS
    let originalAvatarUrl = 'assets/avatars/models/female_avatar.glb'; // Default female avatar
    let currentAvatarUrl = '';
    let currentAvatarGender = 'female'; // Track current avatar gender
    let availableAvatars = {
      female: {
        avatarUrl: 'assets/avatars/models/female_avatar.glb',
        animationsUrl: 'assets/avatars/models/female_animations.glb', // HARDCODED: Female avatar ALWAYS uses female animations
        name: 'Female Avatar',
        gender: 'female'
      },
      male: {
        avatarUrl: 'assets/avatars/models/male_avatar.glb',
        animationsUrl: 'assets/avatars/models/male_animations.glb', // HARDCODED: Male avatar ALWAYS uses male animations
        name: 'Male Avatar',
        gender: 'male'
      }
    };
    let availableVoices = {
      female: {
        id: 'TbMNBJ27fH2U0VgpSNko',
        name: 'Sophia (Female)',
        gender: 'female',
        previewFile: 'assets/audio/voice_previews/ElevenLabs_2025-07-06T17_01_30_Lori - Happy and sweet_pvc_sp100_s50_sb75_se0_b_m2.mp3'
      },
      female_elegant: {
        id: 'x959FyxFeswkQQqFjoPb',
        name: 'Emma (Elegant Female)',
        gender: 'female',
        previewFile: 'assets/audio/voice_previews/ElevenLabs_2025-07-06T17_02_02_Peach - Sweet & Sassy_pvc_sp100_s50_sb75_se0_b_m2.mp3'
      },
      female_young: {
        id: 'aEO01A4wXwd1O8GPgGlF',
        name: 'Aria (Young Female)',
        gender: 'female',
        previewFile: 'assets/audio/voice_previews/ElevenLabs_2025-07-06T17_02_21_Arabella_pvc_sp100_s12_sb100_se0_b_m2.mp3'
      },
      female_mature: {
        id: 'wrxvN1LZJIfL3HHvffqe',
        name: 'Isabella (Mature Female)',
        gender: 'female',
        previewFile: 'assets/audio/voice_previews/ElevenLabs_2025-07-06T17_02_48_Bella - Bubbly Best Friend_pvc_sp100_s30_sb100_se2_b_m2.mp3'
      },
      female_professional: {
        id: 'TgnhEILA8UwUqIMi20rp',
        name: 'Victoria (Professional Female)',
        gender: 'female',
        previewFile: 'assets/audio/voice_previews/ElevenLabs_2025-07-06T17_03_10_Jenna - Warm and Articulate_pvc_sp100_s50_sb75_se100_b_m2.mp3'
      },
      male: {
        id: 'gAMZphRyrWJnLMDnom6H',
        name: 'Alexander (Male)',
        gender: 'male',
        previewFile: 'assets/audio/voice_previews/ElevenLabs_2025-07-06T17_04_21_Keith\'s Voice - Nonchalant Talker_pvc_sp100_s55_sb45_se16_b_m2.mp3'
      },
      male_deep: {
        id: 'KvGNt2kFTJThvOILJU7E',
        name: 'Marcus (Deep Male)',
        gender: 'male',
        previewFile: 'assets/audio/voice_previews/ElevenLabs_2025-07-06T17_05_01_James Voice Clone_pvc_sp100_s19_sb75_se0_b_m2.mp3'
      },
      male_young: {
        id: 'j57KDF72L6gxbLk4sOo5',
        name: 'Jake (Young Male)',
        gender: 'male',
        previewFile: 'assets/audio/voice_previews/ElevenLabs_2025-07-06T17_05_18_George_pvc_sp94_s69_sb37_se0_b_m2.mp3'
      },
      male_mature: {
        id: '4Tha3hqCsECEKz5JttmV',
        name: 'David (Mature Male)',
        gender: 'male',
        previewFile: 'assets/audio/voice_previews/ElevenLabs_2025-07-06T17_05_31_Asad Shah_pvc_sp105_s63_sb61_se50_b_m2.mp3'
      },
      male_professional: {
        id: 'UDgcwmi9QWxuoU7Du5pH',
        name: 'Benjamin (Professional Male)',
        gender: 'male',
        previewFile: 'assets/audio/voice_previews/ElevenLabs_2025-07-06T17_05_59_Steve- American voice for commercials_pvc_sp100_s71_sb62_se79_b_m2.mp3'
      }
    };
    let currentVoiceId = availableVoices.female.id;
    
    // Voice recording variables
    let recognition = null;
    let isRecording = false;
    let recordingStartTime = null;
    let recordingTimeout = null;
    
    // Initialize avatar when page loads
    document.addEventListener('DOMContentLoaded', function() {
      console.log('🚀 VerzTec Chatbot - Initializing...');
      
      // Add basic error handling
      window.addEventListener('error', function(e) {
        console.error('💥 JavaScript Error:', e.error, 'at', e.filename, 'line', e.lineno);
      });
      
      // Check if required elements exist
      const avatarContainer = document.getElementById('avatar-3d');
      if (!avatarContainer) {
        console.error('❌ Avatar container not found!');
        return;
      }
      
      // Check if required scripts are loaded
      if (typeof THREE === 'undefined') {
        console.error('❌ Three.js not loaded!');
        return;
      }
      
      try {
        initializeAvatar();
        setupEventListeners();
        initializeAvatarCustomization();
        initializeVoiceRecognition();
        console.log('✅ VerzTec Chatbot initialized successfully');
      } catch (error) {
        console.error('💥 Error during initialization:', error);
      }
    });
    
    // Cleanup on page unload
    window.addEventListener('beforeunload', function() {
      stopThinkingAnimation();
      
      // Cleanup voice recognition
      if (recognition && isRecording) {
        recognition.stop();
      }
    });
    
    function initializeAvatar() {
      console.log('🎭 initializeAvatar() called');
      
      // Only initialize avatar if it's enabled
      if (!isAvatarEnabled) {
        console.log('Avatar disabled, skipping initialization');
        return;
      }
      
      // Check if AvatarManager class exists
      if (typeof AvatarManager === 'undefined') {
        console.error('❌ AvatarManager class not found! Check if avatar-manager.js is loaded.');
        return;
      }
      
      console.log('✅ AvatarManager class found');
      
      // Set current avatar URL to the selected avatar
      const currentAvatarConfig = availableAvatars[currentAvatarGender];
      currentAvatarUrl = currentAvatarConfig.avatarUrl;
      
      console.log('🎭 Initializing Avatar Manager with:');
      console.log('  - Avatar URL:', currentAvatarUrl);
      console.log('  - Animations URL:', currentAvatarConfig.animationsUrl);
      console.log('  - Gender:', currentAvatarGender);
      console.log('  - Voice ID:', currentVoiceId);
      
      try {
        // Use current voice settings
        avatarManager = new AvatarManager('avatar-3d', {
          elevenlabsApiKey: 'sk_72283c30a844b3d198dda76a38373741c8968217a9472ae7',
          voice: currentVoiceId,
          avatarUrl: currentAvatarUrl,
          animationsUrl: currentAvatarConfig.animationsUrl,
          volume: 1.0 // Initialize with full volume
        });
        
        console.log('✅ AvatarManager created successfully');
        
      } catch (error) {
        console.error('💥 Error creating AvatarManager:', error);
        updateAvatarStatus('Loading Complete');
        return;
      }
      
      // Wait for avatar to be fully initialized
      const checkInitialization = setInterval(() => {
        if (avatarManager && avatarManager.isInitialized) {
          updateAvatarStatus('Ready to help');
          clearInterval(checkInitialization);
          console.log('Avatar manager initialized successfully');
          
          // Set initial voice state
          avatarManager.setVoiceEnabled(isVoiceEnabled);
          
          // Sync volume slider with avatar manager
          const currentVolume = avatarManager.getVolume();
          document.getElementById('avatar-volume-slider').value = currentVolume * 100;
          document.getElementById('avatar-volume-display').textContent = Math.round(currentVolume * 100) + '%';
          
          // Test API key
          avatarManager.testApiKey().then(result => {
            if (result.success) {
              console.log('ElevenLabs API key is working properly');
              updateAvatarStatus('Ready to help - Voice enabled');
            } else {
              console.error('ElevenLabs API key test failed:', result.error);
              updateAvatarStatus('Ready to help - Voice may have issues');
            }
          });
        }
      }, 500);
      
      // Timeout after 10 seconds
      setTimeout(() => {
        clearInterval(checkInitialization);
        if (!avatarManager || !avatarManager.isInitialized) {
          updateAvatarStatus('Avatar failed to load');
          console.error('Avatar initialization timeout');
        }
      }, 10000);
    }
    
    function getAnimationsUrl(gender) {
      // HARDCODED ANIMATION MAPPINGS - NO EXCEPTIONS
      if (gender === 'female') {
        console.log('🎭 HARDCODED: Female avatar MUST use female animations');
        return 'assets/avatars/models/female_animations.glb';
      } else if (gender === 'male') {
        console.log('🎭 HARDCODED: Male avatar MUST use male animations');
        return 'assets/avatars/models/male_animations.glb';
      }
      
      // Default fallback to female animations
      console.log('🎭 HARDCODED: Unknown gender, defaulting to female animations');
      return 'assets/avatars/models/female_animations.glb';
    }
    
    function detectAvatarGender(avatarUrl) {
      // Enhanced gender detection for Ready Player Me avatars
      if (typeof avatarUrl === 'string') {
        // Check if it's one of our local avatars
        if (avatarUrl.includes('male_avatar.glb') || avatarUrl.includes('male')) {
          return 'male';
        }
        if (avatarUrl.includes('female_avatar.glb') || avatarUrl.includes('female')) {
          return 'female';
        }
        
        // For Ready Player Me URLs, use more sophisticated detection
        const urlLower = avatarUrl.toLowerCase();
        
        // Look for gender indicators in filename/path
        if (urlLower.includes('male') || urlLower.includes('man') || urlLower.includes('boy')) {
          return 'male';
        }
        if (urlLower.includes('female') || urlLower.includes('woman') || urlLower.includes('girl')) {
          return 'female';
        }
        
        // For Ready Player Me avatars, we'll analyze the 3D model after it loads
        console.log('Ready Player Me avatar detected, will analyze 3D model for gender detection');
        return 'unknown'; // Will be detected after model loads
      }
      
      // Default fallback
      return 'female';
    }
    
    function analyzeAvatarGender(avatarMesh) {
      // Enhanced 3D avatar mesh analysis to determine gender based on body proportions
      if (!avatarMesh) {
        console.log('No avatar mesh available for analysis');
        return 'female';
      }
      
      try {
        // Get the bounding box of the avatar
        const box = new THREE.Box3().setFromObject(avatarMesh);
        const size = box.getSize(new THREE.Vector3());
        const center = box.getCenter(new THREE.Vector3());
        
        // Calculate body proportions
        const height = size.y;
        const width = size.x;
        const depth = size.z;
        
        // Advanced ratio calculations
        const shoulderHipRatio = width / height;
        const aspectRatio = width / depth;
        const volumeRatio = (width * depth) / (height * height);
        
        // Analyze specific body regions if possible
        let shoulderWidth = 0;
        let hipWidth = 0;
        let chestDepth = 0;
        
        // Try to find specific body parts for more accurate detection
        avatarMesh.traverse((child) => {
          if (child.isMesh && child.name) {
            const name = child.name.toLowerCase();
            
            // Look for shoulder/chest area
            if (name.includes('shoulder') || name.includes('chest') || name.includes('torso')) {
              const childBox = new THREE.Box3().setFromObject(child);
              const childSize = childBox.getSize(new THREE.Vector3());
              shoulderWidth = Math.max(shoulderWidth, childSize.x);
              chestDepth = Math.max(chestDepth, childSize.z);
            }
            
            // Look for hip area
            if (name.includes('hip') || name.includes('pelvis') || name.includes('waist')) {
              const childBox = new THREE.Box3().setFromObject(child);
              const childSize = childBox.getSize(new THREE.Vector3());
              hipWidth = Math.max(hipWidth, childSize.x);
            }
          }
        });
        
        // Calculate confidence score
        let maleScore = 0;
        let femaleScore = 0;
        
        console.log('Enhanced Avatar Analysis:', {
          height: height.toFixed(2),
          width: width.toFixed(2),
          depth: depth.toFixed(2),
          shoulderHipRatio: shoulderHipRatio.toFixed(3),
          aspectRatio: aspectRatio.toFixed(3),
          volumeRatio: volumeRatio.toFixed(3),
          shoulderWidth: shoulderWidth.toFixed(2),
          hipWidth: hipWidth.toFixed(2),
          chestDepth: chestDepth.toFixed(2)
        });
        
        // Scoring system for gender detection
        
        // 1. Shoulder-to-hip ratio (primary indicator)
        if (shoulderHipRatio > 0.48) {
          maleScore += 3;
        } else if (shoulderHipRatio > 0.45) {
          maleScore += 2;
        } else if (shoulderHipRatio > 0.42) {
          maleScore += 1;
        } else if (shoulderHipRatio < 0.38) {
          femaleScore += 3;
        } else if (shoulderHipRatio < 0.41) {
          femaleScore += 2;
        } else {
          femaleScore += 1;
        }
        
        // 2. Body depth (secondary indicator)
        if (depth > 0.28) {
          maleScore += 2;
        } else if (depth > 0.25) {
          maleScore += 1;
        } else if (depth < 0.22) {
          femaleScore += 2;
        } else {
          femaleScore += 1;
        }
        
        // 3. Overall body volume ratio
        if (volumeRatio > 0.25) {
          maleScore += 2;
        } else if (volumeRatio < 0.20) {
          femaleScore += 2;
        }
        
        // 4. Aspect ratio (width vs depth)
        if (aspectRatio > 1.8) {
          maleScore += 1;
        } else if (aspectRatio < 1.5) {
          femaleScore += 1;
        }
        
        // 5. Specific body part analysis
        if (shoulderWidth > 0 && hipWidth > 0) {
          const shoulderHipMeasuredRatio = shoulderWidth / hipWidth;
          if (shoulderHipMeasuredRatio > 1.15) {
            maleScore += 2;
          } else if (shoulderHipMeasuredRatio < 1.05) {
            femaleScore += 2;
          }
        }
        
        // 6. Chest depth analysis
        if (chestDepth > 0.15) {
          maleScore += 1;
        } else if (chestDepth < 0.12) {
          femaleScore += 1;
        }
        
        const totalScore = maleScore + femaleScore;
        const confidence = Math.max(maleScore, femaleScore) / totalScore;
        
        console.log('Gender Detection Scoring:', {
          maleScore: maleScore,
          femaleScore: femaleScore,
          confidence: (confidence * 100).toFixed(1) + '%',
          decision: maleScore > femaleScore ? 'MALE' : 'FEMALE'
        });
        
        // Determine gender based on scoring
        if (maleScore > femaleScore) {
          console.log(`✅ Detected as MALE (confidence: ${(confidence * 100).toFixed(1)}%)`);
          return 'male';
        } else {
          console.log(`✅ Detected as FEMALE (confidence: ${(confidence * 100).toFixed(1)}%)`);
          return 'female';
        }
        
      } catch (error) {
        console.error('Error analyzing avatar gender:', error);
        return 'female'; // Default fallback
      }
    }
    
    function suggestVoiceChange(detectedGender) {
      // Show a suggestion to change voice based on detected gender
      const currentVoiceGender = getCurrentVoiceGender();
      
      if (detectedGender !== currentVoiceGender) {
        const suggestedVoice = availableVoices[detectedGender];
        if (suggestedVoice) {
          updateAvatarStatus(`💡 Tip: Consider switching to ${suggestedVoice.name} voice for better matching`);
          setTimeout(() => {
            updateAvatarStatus('Ready to help');
          }, 5000);
        }
      }
    }
    
    function getCurrentVoiceGender() {
      // Find current voice gender
      for (const [gender, voice] of Object.entries(availableVoices)) {
        if (voice.id === currentVoiceId) {
          return gender;
        }
      }
      return 'female'; // Default
    }
    
    function setupEventListeners() {
      console.log('🎛️ Setting up event listeners...');
      
      try {
        // Avatar toggle
        const toggleAvatarBtn = document.getElementById('toggle-avatar');
        if (toggleAvatarBtn) {
          toggleAvatarBtn.addEventListener('click', function() {
            isAvatarEnabled = !isAvatarEnabled;
            toggleAvatar();
          });
          console.log('✅ Avatar toggle button listener added');
        } else {
          console.warn('⚠️ Avatar toggle button not found');
        }
        
        // Show avatar button (in chat header)
        const showAvatarBtn = document.getElementById('show-avatar');
        if (showAvatarBtn) {
          showAvatarBtn.addEventListener('click', function() {
            isAvatarEnabled = true;
            toggleAvatar();
          });
          console.log('✅ Show avatar button listener added');
        } else {
          console.warn('⚠️ Show avatar button not found');
        }
        
        // Avatar circular button selection
        console.log('🎯 Setting up avatar circular buttons...');
        const avatarButtons = document.querySelectorAll('.avatar-circle-btn[data-avatar]');
        console.log('Found avatar buttons:', avatarButtons.length);
        
        avatarButtons.forEach((btn, index) => {
          console.log(`Button ${index + 1}:`, btn.dataset.avatar, btn);
          
          // Add both click and touchstart events for better mobile support
          btn.addEventListener('click', function(e) {
            console.log('🔴 Avatar button clicked:', this.dataset.avatar);
            e.preventDefault();
            e.stopPropagation();
            
            const selectedAvatar = this.dataset.avatar;
            
            // Remove active class from all avatar buttons
            document.querySelectorAll('.avatar-circle-btn[data-avatar]').forEach(b => {
              b.classList.remove('active');
            });
            
            // Add active class to clicked button
            this.classList.add('active');
            
            // Switch to the selected avatar
            switchAvatar(selectedAvatar);
          });
          
          btn.addEventListener('touchstart', function(e) {
            console.log('🔴 Avatar button touched:', this.dataset.avatar);
            e.preventDefault();
          });
        });
        
        // Customize avatar button
        const customizeBtn = document.getElementById('customize-avatar-btn');
        console.log('Found customize button:', customizeBtn);
        if (customizeBtn) {
          customizeBtn.addEventListener('click', function(e) {
            console.log('🔴 Customize button clicked');
            e.preventDefault();
            e.stopPropagation();
            openAvatarCreator();
          });
          
          customizeBtn.addEventListener('touchstart', function(e) {
            console.log('🔴 Customize button touched');
            e.preventDefault();
          });
        }
        
        // Test function to verify buttons are accessible
        setTimeout(() => {
          console.log('🧪 Testing button accessibility...');
          avatarButtons.forEach((btn, index) => {
            const rect = btn.getBoundingClientRect();
            console.log(`Button ${index + 1} position:`, {
              top: rect.top,
              left: rect.left,
              width: rect.width,
              height: rect.height,
              visible: rect.width > 0 && rect.height > 0
            });
          });
        }, 1000);
      
      // Volume control functionality
      let isMuted = false;
      let previousVolume = 1.0;
      
      document.getElementById('avatar-volume-slider').addEventListener('input', function() {
        const volume = this.value / 100;
        setAvatarVolume(volume);
        document.getElementById('avatar-volume-display').textContent = this.value + '%';
        
        // Update mute button state
        if (volume === 0) {
          isMuted = true;
        } else if (isMuted) {
          isMuted = false;
        }
      });
      
      // Background color change functionality
      const backgroundToggle = document.getElementById('background-toggle');
      const backgroundMenu = document.getElementById('background-menu');
      
      // Toggle dropdown
      backgroundToggle.addEventListener('click', function(e) {
        e.stopPropagation();
        backgroundMenu.classList.toggle('show');
      });
      
      // Close dropdown when clicking outside
      document.addEventListener('click', function() {
        backgroundMenu.classList.remove('show');
      });
      
      // Handle background selection
      document.querySelectorAll('.background-menu-item').forEach(item => {
        item.addEventListener('click', function(e) {
          e.stopPropagation();
          
          // Remove active class from all items
          document.querySelectorAll('.background-menu-item').forEach(menuItem => {
            menuItem.classList.remove('active');
          });
          
          // Add active class to clicked item
          this.classList.add('active');
          
          // Update current selection display
          const newBg = this.dataset.bg;
          const newName = this.dataset.name;
          
          document.querySelector('.background-current-color').style.background = newBg;
          
          // Change avatar background
          const avatarContainer = document.querySelector('.avatar-container');
          avatarContainer.style.background = newBg;
          
          // Don't close dropdown - let user manually close it
          updateAvatarStatus(`Background changed to ${newName}`);
        });
      });
      
      // Prevent dropdown from closing when clicking inside
      backgroundMenu.addEventListener('click', function(e) {
        e.stopPropagation();
      });
      
      // Voice toggle (main avatar controls)
      document.getElementById('toggle-voice').addEventListener('click', function() {
        isVoiceEnabled = !isVoiceEnabled;
        updateVoiceButton(this);
        updateAvatarStatus(isVoiceEnabled ? 'Voice enabled' : 'Voice disabled');
        
        // Update avatar manager voice state
        if (avatarManager) {
          avatarManager.setVoiceEnabled(isVoiceEnabled);
        }
      });
      
      // Voice toggle (chat header controls)
      document.getElementById('chat-toggle-voice').addEventListener('click', function() {
        isVoiceEnabled = !isVoiceEnabled;
        updateVoiceButton(this);
        updateAvatarStatus(isVoiceEnabled ? 'Voice enabled' : 'Voice disabled');
        
        // Also update the main avatar voice button
        const mainVoiceButton = document.getElementById('toggle-voice');
        updateVoiceButton(mainVoiceButton);
        
        // Update avatar manager voice state
        if (avatarManager) {
          avatarManager.setVoiceEnabled(isVoiceEnabled);
        }
      });
      
      // Speed control buttons (both sets)
      document.querySelectorAll('.speed-btn').forEach(button => {
        button.addEventListener('click', function() {
          // Don't change speed while chatbot is busy
          if (isChatbotBusy) return;
          
          // Find the parent container to update buttons within the same group
          const parentContainer = this.closest('.btn-group');
          const allSpeedButtons = parentContainer.querySelectorAll('.speed-btn');
          
          // Remove active class from all speed buttons in this group
          allSpeedButtons.forEach(btn => btn.classList.remove('active'));
          
          // Add active class to clicked button
          this.classList.add('active');
          
          // Update speed
          currentSpeed = parseFloat(this.dataset.speed);
          console.log('🎛️ Speed button clicked - Speed changed to:', currentSpeed + 'x');
          updateAvatarStatus(`Speed: ${currentSpeed}x`);
          
          // Update avatar manager speed if available
          if (avatarManager) {
            console.log('🎛️ Calling avatarManager.setSpeed with:', currentSpeed);
            avatarManager.setSpeed(currentSpeed);
          } else {
            console.log('🎛️ avatarManager not available');
          }
          
          // Sync both speed control groups
          syncSpeedButtons(currentSpeed);
        });
      });
      
      console.log('✅ All event listeners set up successfully');
    } catch (error) {
      console.error('💥 Error setting up event listeners:', error);
    }
    }
    
    function toggleAvatar() {
      const chatSection = document.getElementById('chat-section');
      const avatarColumn = document.getElementById('avatar-column');
      const avatarContainer = document.querySelector('.avatar-container');
      const chatPanel = document.querySelector('.chat-panel');
      const showAvatarBtn = document.getElementById('show-avatar');
      const chatVoiceBtn = document.getElementById('chat-toggle-voice');
      const chatSpeedControls = document.getElementById('chat-speed-controls');
      const toggleAvatarBtn = document.getElementById('toggle-avatar');
      
      if (isAvatarEnabled) {
        // Show avatar
        chatSection.classList.remove('avatar-hidden');
        avatarContainer.classList.remove('hidden');
        chatPanel.classList.remove('expanded');
        
        // Hide chat header controls
        showAvatarBtn.style.display = 'none';
        chatVoiceBtn.style.display = 'none';
        chatSpeedControls.style.display = 'none';
        
        // Update avatar toggle button
        toggleAvatarBtn.innerHTML = '<i class="fa fa-robot"></i> Avatar On';
        
        // Initialize avatar if not already done
        if (!avatarManager) {
          initializeAvatar();
        } else if (avatarManager.isInitialized) {
          // If avatar already exists, sync voice state
          avatarManager.setVoiceEnabled(isVoiceEnabled);
        }
        
        updateAvatarStatus('Avatar enabled');
      } else {
        // Hide avatar
        chatSection.classList.add('avatar-hidden');
        avatarContainer.classList.add('hidden');
        chatPanel.classList.add('expanded');
        
        // Show chat header controls
        showAvatarBtn.style.display = 'inline-block';
        chatVoiceBtn.style.display = 'inline-block';
        chatSpeedControls.style.display = 'inline-flex';
        
        // Update avatar toggle button
        toggleAvatarBtn.innerHTML = '<i class="fa fa-robot"></i> Avatar Off';
        
        // Stop any avatar activities
        if (avatarManager && avatarManager.isInitialized) {
          avatarManager.stopThinking();
          avatarManager.switchAnimation('idle');
          avatarManager.isSpeaking = false;
          avatarManager.isThinking = false;
        }
        
        // Sync voice button state
        updateVoiceButton(chatVoiceBtn);
        
        // Sync speed buttons
        syncSpeedButtons(currentSpeed);
        
        updateAvatarStatus('Avatar disabled');
      }
    }
    
    function updateVoiceButton(button) {
      if (isVoiceEnabled) {
        button.innerHTML = '<i class="fa fa-volume-up"></i> Voice On';
      } else {
        button.innerHTML = '<i class="fa fa-volume-off"></i> Voice Off';
      }
    }
    
    function syncSpeedButtons(speed) {
      // Update both avatar controls and chat header controls
      document.querySelectorAll('.speed-btn').forEach(btn => {
        btn.classList.remove('active');
        if (parseFloat(btn.dataset.speed) === speed) {
          btn.classList.add('active');
        }
      });
    }
    
    function updateAvatarStatus(status) {
      document.querySelector('.avatar-status').textContent = status;
    }
    
    function setAvatarVolume(volume) {
      if (avatarManager) {
        avatarManager.setVolume(volume);
      } else {
        // Fallback for when avatar manager isn't initialized yet
        const audioElements = document.querySelectorAll('audio');
        audioElements.forEach(audio => {
          audio.volume = volume;
        });
      }
    }
    
    function disableUserInput() {
      const userInput = document.getElementById('user-input');
      const sendButton = document.querySelector('.chat-input-group .btn-icon');
      const voiceButton = document.getElementById('voice-record-btn');
      
      userInput.disabled = true;
      userInput.placeholder = 'Please wait...';
      sendButton.style.pointerEvents = 'none';
      sendButton.style.opacity = '0.5';
      
      if (voiceButton) {
        voiceButton.style.pointerEvents = 'none';
        voiceButton.style.opacity = '0.5';
      }
      
      // Stop any ongoing voice recording
      if (isRecording) {
        stopVoiceRecording();
      }
      
      isChatbotBusy = true;
    }
    
    function enableUserInput() {
      const userInput = document.getElementById('user-input');
      const sendButton = document.querySelector('.chat-input-group .btn-icon');
      const voiceButton = document.getElementById('voice-record-btn');
      
      userInput.disabled = false;
      userInput.placeholder = 'Ask anything...';
      sendButton.style.pointerEvents = 'auto';
      sendButton.style.opacity = '1';
      
      if (voiceButton) {
        voiceButton.style.pointerEvents = 'auto';
        voiceButton.style.opacity = '1';
      }
      
      isChatbotBusy = false;
    }

    function handleKeyPress(event) {
      if (event.key === 'Enter' && !isChatbotBusy) {
        sendMessage();
      }
    }

    // Voice Recording Functions
    function initializeVoiceRecognition() {
      console.log('🎤 Initializing voice recognition...');
      
      // Check if the browser supports Web Speech API
      if (!('webkitSpeechRecognition' in window) && !('SpeechRecognition' in window)) {
        console.error('❌ Web Speech API not supported in this browser');
        updateVoiceRecordingStatus('Voice recording not supported in this browser');
        return false;
      }
      
      // Initialize speech recognition
      const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
      recognition = new SpeechRecognition();
      
      // Configure recognition settings
      recognition.continuous = true;
      recognition.interimResults = true;
      recognition.lang = 'en-US';
      recognition.maxAlternatives = 1;
      
      // Event handlers
      recognition.onstart = function() {
        console.log('🎤 Voice recognition started');
        isRecording = true;
        recordingStartTime = Date.now();
        updateVoiceRecordingUI(true);
        updateVoiceRecordingStatus('Listening... Click microphone to stop');
        
        // Auto-stop after 30 seconds to prevent indefinite recording
        recordingTimeout = setTimeout(() => {
          if (isRecording) {
            stopVoiceRecording();
          }
        }, 30000);
      };
      
      recognition.onresult = function(event) {
        let interimTranscript = '';
        let finalTranscript = '';
        
        for (let i = event.resultIndex; i < event.results.length; i++) {
          const transcript = event.results[i][0].transcript;
          if (event.results[i].isFinal) {
            finalTranscript += transcript + ' ';
          } else {
            interimTranscript += transcript;
          }
        }
        
        // Update the input field with the transcription
        const userInput = document.getElementById('user-input');
        const currentValue = userInput.value;
        
        // Replace any previous interim results with new ones
        const baseText = currentValue.replace(/\[.*?\]$/, '').trim();
        
        if (finalTranscript) {
          userInput.value = (baseText + ' ' + finalTranscript).trim();
          console.log('🎤 Final transcript added:', finalTranscript.trim());
        }
        
        if (interimTranscript) {
          userInput.value = (baseText + ' ' + finalTranscript + '[' + interimTranscript + ']').trim();
        }
        
        // Show status update
        if (interimTranscript) {
          updateVoiceRecordingStatus('Listening... "' + interimTranscript + '"');
        }
      };
      
      recognition.onerror = function(event) {
        console.error('🎤 Voice recognition error:', event.error);
        let errorMessage = 'Voice recognition error: ';
        
        switch(event.error) {
          case 'no-speech':
            errorMessage += 'No speech detected';
            break;
          case 'audio-capture':
            errorMessage += 'No microphone found';
            break;
          case 'not-allowed':
            errorMessage += 'Microphone permission denied';
            break;
          case 'network':
            errorMessage += 'Network error';
            break;
          default:
            errorMessage += event.error;
        }
        
        updateVoiceRecordingStatus(errorMessage);
        stopVoiceRecording();
      };
      
      recognition.onend = function() {
        console.log('🎤 Voice recognition ended');
        if (isRecording) {
          // Clean up any interim results in brackets
          const userInput = document.getElementById('user-input');
          userInput.value = userInput.value.replace(/\[.*?\]$/, '').trim();
          
          stopVoiceRecording();
        }
      };
      
      console.log('✅ Voice recognition initialized successfully');
      return true;
    }
    
    function toggleVoiceRecording() {
      console.log('🎤 Toggle voice recording clicked, isRecording:', isRecording);
      
      if (isChatbotBusy) {
        updateVoiceRecordingStatus('Please wait for the current conversation to finish');
        return;
      }
      
      if (isRecording) {
        stopVoiceRecording();
      } else {
        startVoiceRecording();
      }
    }
    
    function startVoiceRecording() {
      console.log('🎤 Starting voice recording...');
      
      // Initialize recognition if not already done
      if (!recognition) {
        if (!initializeVoiceRecognition()) {
          return;
        }
      }
      
      // Request microphone permission and start recording
      try {
        recognition.start();
        console.log('🎤 Voice recognition start() called');
      } catch (error) {
        console.error('🎤 Error starting voice recognition:', error);
        updateVoiceRecordingStatus('Failed to start voice recording: ' + error.message);
      }
    }
    
    function stopVoiceRecording() {
      console.log('🎤 Stopping voice recording...');
      
      if (recognition && isRecording) {
        recognition.stop();
      }
      
      // Clear timeout
      if (recordingTimeout) {
        clearTimeout(recordingTimeout);
        recordingTimeout = null;
      }
      
      // Update UI
      isRecording = false;
      updateVoiceRecordingUI(false);
      
      // Immediately reset placeholder text
      const userInput = document.getElementById('user-input');
      userInput.placeholder = 'Review your message and press Send';
      
      // Calculate recording duration and update status
      if (recordingStartTime) {
        const duration = ((Date.now() - recordingStartTime) / 1000).toFixed(1);
        updateVoiceRecordingStatus(`Recording stopped (${duration}s). Review and send your message.`);
        recordingStartTime = null;
      } else {
        updateVoiceRecordingStatus('Recording stopped. Review and send your message.');
      }
      
      // Reset placeholder back to original after 3 seconds
      setTimeout(() => {
        if (userInput.placeholder !== 'Listening...' && !isChatbotBusy) {
          userInput.placeholder = 'Ask anything...';
        }
      }, 3000);
      
      // Focus on the input field so user can review the transcription
      userInput.focus();
      
      console.log('🎤 Voice recording stopped, final text:', userInput.value);
    }
    
    function updateVoiceRecordingUI(recording) {
      const recordBtn = document.getElementById('voice-record-btn');
      const recordIcon = recordBtn.querySelector('i');
      
      if (recording) {
        recordBtn.classList.add('recording');
        recordBtn.style.backgroundColor = '#dc3545';
        recordBtn.style.color = 'white';
        recordBtn.title = 'Stop recording';
        recordIcon.className = 'fa fa-stop';
        
        // Add pulsing animation
        recordBtn.style.animation = 'pulse 1.5s infinite';
      } else {
        recordBtn.classList.remove('recording');
        recordBtn.style.backgroundColor = '';
        recordBtn.style.color = '';
        recordBtn.style.animation = '';
        recordBtn.title = 'Start voice recording';
        recordIcon.className = 'fa fa-microphone';
      }
    }
    
    function updateVoiceRecordingStatus(message) {
      console.log('🎤 Voice status:', message);
      
      // You can update avatar status or show a toast notification
      if (typeof updateAvatarStatus === 'function') {
        updateAvatarStatus(message);
      }
      
      // Also update placeholder text temporarily
      const userInput = document.getElementById('user-input');
      const originalPlaceholder = 'Ask anything...'; // Use the default placeholder
      
      if (message.includes('Listening')) {
        userInput.placeholder = 'Listening...';
      } else if (message.includes('stopped')) {
        userInput.placeholder = 'Review your message and press Send';
        setTimeout(() => {
          userInput.placeholder = originalPlaceholder;
        }, 3000);
      } else if (message.includes('error') || message.includes('not supported')) {
        userInput.placeholder = message;
        setTimeout(() => {
          userInput.placeholder = originalPlaceholder;
        }, 5000);
      } else {
        // For any other message, reset to original placeholder
        userInput.placeholder = originalPlaceholder;
      }
    }

    async function sendMessage() {
      // Don't send if chatbot is busy
      if (isChatbotBusy) {
        return;
      }
      
      const userInput = document.getElementById('user-input');
      const message = userInput.value.trim();
      
      if (message === '') {
        return;
      }

      // Disable user input while processing
      disableUserInput();

      // Add user message to chat
      addMessageToChat(message, 'user');
      
      // Clear input
      userInput.value = '';

      // Always show thinking animation immediately in chat UI
      console.log('🤔 Showing thinking animation immediately...');
      showThinking(); // Show thinking animation in chat immediately
      
      // Trigger avatar thinking animation only if avatar is ready AND voice is enabled
      if (avatarManager && avatarManager.isInitialized && isVoiceEnabled && isAvatarEnabled) {
        console.log('🤔 Triggering avatar thinking animation...');
        avatarManager.startThinking();
        updateAvatarStatus('Thinking...');
      } else {
        // Avatar not ready or voice disabled, but chat thinking animation is still shown
        if (!isVoiceEnabled) {
          updateAvatarStatus('Voice disabled - processing...');
        } else if (!isAvatarEnabled) {
          updateAvatarStatus('Avatar disabled - processing...');
        } else {
          updateAvatarStatus('Processing...');
        }
      }

      const userId = <?php echo json_encode($user_id); ?>;

      // Send message to chatbot API
      try {
        console.log('🔄 Sending message to API:', message);
        const response = await fetch('http://localhost:8000/chat', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({ user_id: userId, question: message })
        });
        
        console.log('📡 API Response status:', response.status);
        
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        console.log('📦 API Response data:', data);
        
        // Check if the response has the expected structure
        if (!data || (!data.answer && !data.response)) {
          console.error('❌ Invalid API response structure:', data);
          throw new Error('Invalid response from chatbot API');
        }
        
        // Get the answer from the response (try both 'answer' and 'response' fields)
        const botAnswer = data.answer || data.response || 'Sorry, I could not generate a response.';
        console.log('🤖 Bot answer:', botAnswer.substring(0, 100) + '...');
        
        // Store reference file info for later use (after text is complete)
        let referenceFile = null;
        if (data.reference_file && data.reference_file.name) {
          referenceFile = {
            url: data.reference_file.url,
            name: data.reference_file.name
          };
        }
        
        // Speak the response if voice is enabled and avatar is ready
        if (isVoiceEnabled && isAvatarEnabled && avatarManager && avatarManager.isInitialized) {
          try {
            console.log('Starting synchronized speech with speed:', currentSpeed + 'x', 'for text:', botAnswer.substring(0, 50) + '...');
            
            // Variable to track if message box has been created
            let botMessageDiv = null;
            let isFirstLetterReady = false;
            
            // Use the improved speakWithTextStream method with callback for when first letter is ready
            await avatarManager.speakWithTextStream(botAnswer, (streamedText) => {
              // Check if this is the signal that first letter is ready (empty string)
              if (streamedText === '' && !isFirstLetterReady) {
                console.log('📝 First letter ready signal received, creating message box...');
                hideThinking(); // Hide thinking animation when text bubble appears
                
                botMessageDiv = document.createElement('div');
                botMessageDiv.className = 'bot-bubble';
                botMessageDiv.innerHTML = '<strong>VerzTec Assistant:</strong> ';
                
                const chatContainer = document.getElementById('chat-container');
                chatContainer.appendChild(botMessageDiv);
                chatContainer.scrollTop = chatContainer.scrollHeight;
                
                updateAvatarStatus('Speaking...');
                isFirstLetterReady = true;
                return; // Don't update content yet
              }
              
              // Update the message content with streamed text (only after box is created)
              if (botMessageDiv && streamedText.length > 0) {
                botMessageDiv.innerHTML = `<strong>VerzTec Assistant:</strong> ${streamedText}`;
                const chatContainer = document.getElementById('chat-container');
                chatContainer.scrollTop = chatContainer.scrollHeight;
              }
            }, currentSpeed); // Pass speed to speakWithTextStream
            
            // Text is fully generated, no need to hide thinking again
            console.log('🎯 Text fully generated');
            
            // Now add reference file link after text is fully generated
            if (referenceFile) {
              console.log('📄 Adding reference file after text completion...');
              addReferenceLink(referenceFile.url, referenceFile.name);
            }
            
            updateAvatarStatus('Ready to help');
            enableUserInput(); // Re-enable input after response is complete
            
            updateAvatarStatus('Ready to help');
          } catch (error) {
            console.error('Speech failed:', error);
            hideThinking(); // Hide thinking animation on error
            // If speech fails, show the text normally
            addMessageToChat(botAnswer, 'bot');
            // Add reference file after text is shown
            if (referenceFile) {
              addReferenceLink(referenceFile.url, referenceFile.name);
            }
            updateAvatarStatus('Speech failed, but text is shown');
            enableUserInput(); // Re-enable input after error
          }
        } else {
          hideThinking(); // Hide thinking animation if avatar not ready or voice disabled
          // Voice disabled or avatar not ready, just show text normally
          addMessageToChat(botAnswer, 'bot');
          // Add reference file after text is shown
          if (referenceFile) {
            addReferenceLink(referenceFile.url, referenceFile.name);
          }
          
          if (!isVoiceEnabled) {
            updateAvatarStatus('Voice disabled - text only');
          } else if (!isAvatarEnabled) {
            updateAvatarStatus('Avatar disabled - text only');
          } else {
            updateAvatarStatus('Voice not available');
          }
          enableUserInput(); // Re-enable input when done
        }
        
      } catch (error) {
        hideThinking(); // Hide thinking animation on error
        console.error('Error:', error);
        
        // More specific error messages
        let errorMessage = 'Sorry, I encountered an error. ';
        if (error.message.includes('Failed to fetch')) {
          errorMessage += 'Cannot connect to the chatbot server. Please make sure it is running on port 8000.';
        } else if (error.message.includes('HTTP error')) {
          errorMessage += `Server responded with error: ${error.message}`;
        } else if (error.message.includes('Invalid response')) {
          errorMessage += 'The server returned an invalid response format.';
        } else {
          errorMessage += `${error.message}`;
        }
        
        addMessageToChat(errorMessage, 'bot');
        updateAvatarStatus('Connection error');
        enableUserInput(); // Re-enable input after error
        
        // Reset avatar state on error
        if (avatarManager && avatarManager.isInitialized && isVoiceEnabled && isAvatarEnabled) {
          avatarManager.stopThinking();
        }
      }
    }

    function addMessageToChat(message, sender) {
      const chatContainer = document.getElementById('chat-container');
      const messageDiv = document.createElement('div');
      
      if (sender === 'user') {
        messageDiv.className = 'user-bubble';
        messageDiv.innerHTML = `<strong>You:</strong> ${message}`;
      } else {
        messageDiv.className = 'bot-bubble';
        messageDiv.innerHTML = `<strong>VerzTec Assistant:</strong> ${message}`;
      }
      
      chatContainer.appendChild(messageDiv);
      chatContainer.scrollTop = chatContainer.scrollHeight;
    }

    function addReferenceLink(url, filename) {
      const chatContainer = document.getElementById('chat-container');
      const linkDiv = document.createElement('div');
      
      linkDiv.className = 'bot-bubble';
      linkDiv.innerHTML = `
        <small><i class="fa fa-file-pdf"></i> Reference: <a href="${url}" target="_blank" style="color: #0066cc; text-decoration: underline;">${filename}</a></small>
      `;
      
      chatContainer.appendChild(linkDiv);
      chatContainer.scrollTop = chatContainer.scrollHeight;
    }

    function showLoading() {
      const chatContainer = document.getElementById('chat-container');
      const loadingDiv = document.createElement('div');
      loadingDiv.id = 'loading-message';
      loadingDiv.className = 'bot-bubble';
      loadingDiv.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Thinking...';
      chatContainer.appendChild(loadingDiv);
      chatContainer.scrollTop = chatContainer.scrollHeight;
    }

    function hideLoading() {
      const loadingMessage = document.getElementById('loading-message');
      if (loadingMessage) {
        loadingMessage.remove();
      }
    }

    function showThinking() {
      const chatContainer = document.getElementById('chat-container');
      const thinkingDiv = document.createElement('div');
      thinkingDiv.id = 'thinking-message';
      thinkingDiv.className = 'bot-bubble';
      thinkingDiv.innerHTML = `
        <div style="display: flex; align-items: center;">
          <strong>VerzTec Assistant:</strong>&nbsp;<span style="color: #666; font-style: italic;">thinking</span>
          <div class="thinking-animation">
            <span class="thinking-dot">●</span>
            <span class="thinking-dot">●</span>
            <span class="thinking-dot">●</span>
          </div>
        </div>
      `;
      chatContainer.appendChild(thinkingDiv);
      chatContainer.scrollTop = chatContainer.scrollHeight;
      
      // Start the thinking animation
      startThinkingAnimation();
    }

    function hideThinking() {
      const thinkingMessage = document.getElementById('thinking-message');
      if (thinkingMessage) {
        thinkingMessage.remove();
      }
      stopThinkingAnimation();
    }

    let thinkingAnimationInterval;

    function startThinkingAnimation() {
      let dotIndex = 0;
      thinkingAnimationInterval = setInterval(() => {
        const dots = document.querySelectorAll('.thinking-dot');
        if (dots.length > 0) {
          // Reset all dots
          dots.forEach(dot => {
            dot.classList.remove('active');
          });
          
          // Activate current dot
          dots[dotIndex].classList.add('active');
          
          dotIndex = (dotIndex + 1) % dots.length;
        }
      }, 350); // Slightly faster for more responsive feel
    }

    function stopThinkingAnimation() {
      if (thinkingAnimationInterval) {
        clearInterval(thinkingAnimationInterval);
        thinkingAnimationInterval = null;
      }
    }
    
    // Avatar customization system functions
    function toggleAvatarMenu() {
      const menu = document.getElementById('avatar-menu');
      isAvatarMenuOpen = !isAvatarMenuOpen;
      
      if (isAvatarMenuOpen) {
        menu.classList.add('show');
      } else {
        menu.classList.remove('show');
      }
    }
    
    function switchAvatar(avatarType) {
      if (!availableAvatars[avatarType]) {
        console.error('Unknown avatar type:', avatarType);
        return;
      }
      
      const selectedAvatar = availableAvatars[avatarType];
      
      console.log('🔄 Switching to avatar:', {
        type: avatarType,
        name: selectedAvatar.name,
        gender: selectedAvatar.gender,
        avatarUrl: selectedAvatar.avatarUrl,
        animationsUrl: selectedAvatar.animationsUrl
      });
      
      // Update current avatar configuration
      currentAvatarGender = selectedAvatar.gender;
      currentAvatarUrl = selectedAvatar.avatarUrl;
      
      // Update active circular button
      document.querySelectorAll('.avatar-circle-btn[data-avatar]').forEach(btn => {
        btn.classList.remove('active');
      });
      document.querySelector(`.avatar-circle-btn[data-avatar="${avatarType}"]`).classList.add('active');
      
      // Auto-switch voice to match gender
      const matchingVoice = availableVoices[selectedAvatar.gender];
      if (matchingVoice && currentVoiceId !== matchingVoice.id) {
        currentVoiceId = matchingVoice.id;
      }
      
      // Update status and load new avatar
      updateAvatarStatus('🔄 Switching to ' + selectedAvatar.name + '...');
      
      // Load the new avatar
      if (avatarManager) {
        if (typeof avatarManager.loadNewAvatar === 'function') {
          console.log('🎭 Loading new avatar using loadNewAvatar method');
          avatarManager.loadNewAvatar(selectedAvatar.avatarUrl).then(() => {
            console.log('✅ Avatar loaded, now setting voice and animations');
            console.log('  - Setting voice to:', currentVoiceId);
            console.log('  - Setting animations to:', selectedAvatar.animationsUrl);
            
            // Update voice and animations
            avatarManager.setVoice(currentVoiceId);
            if (avatarManager.setAnimationsUrl) {
              return avatarManager.setAnimationsUrl(selectedAvatar.animationsUrl);
            }
          }).then(() => {
            console.log('✅ Avatar switch completed successfully');
            updateAvatarStatus('✅ Switched to ' + selectedAvatar.name);
            setTimeout(() => {
              updateAvatarStatus('Ready to help');
            }, 2000);
          }).catch(error => {
            console.error('Error switching avatar:', error);
            updateAvatarStatus('❌ Error switching avatar');
          });
        } else {
          console.log('🎭 Reinitializing avatar manager (fallback method)');
          // Fallback: reinitialize avatar manager
          const avatarContainer = document.getElementById('avatar-3d');
          avatarContainer.innerHTML = '';
          
          console.log('🎭 Creating new AvatarManager with:');
          console.log('  - Avatar URL:', selectedAvatar.avatarUrl);
          console.log('  - Animations URL:', selectedAvatar.animationsUrl);
          console.log('  - Voice ID:', currentVoiceId);
          
          avatarManager = new AvatarManager('avatar-3d', {
            elevenlabsApiKey: 'sk_72283c30a844b3d198dda76a38373741c8968217a9472ae7',
            voice: currentVoiceId,
            avatarUrl: selectedAvatar.avatarUrl,
            animationsUrl: selectedAvatar.animationsUrl,
            volume: document.getElementById('avatar-volume-slider').value / 100
          });
          
          // Wait for reinitialization
          const checkReinitialization = setInterval(() => {
            if (avatarManager && avatarManager.isInitialized) {
              clearInterval(checkReinitialization);
              updateAvatarStatus('✅ Switched to ' + selectedAvatar.name);
              setTimeout(() => {
                updateAvatarStatus('Ready to help');
              }, 2000);
            }
          }, 500);
        }
      }
      
      console.log('Switched to avatar:', selectedAvatar);
    }
    
    function changeVoice(voiceType) {
      if (!availableVoices[voiceType]) {
        console.error('Unknown voice type:', voiceType);
        return;
      }
      
      const selectedVoice = availableVoices[voiceType];
      
      // Update current voice
      currentVoiceId = selectedVoice.id;
      currentAvatarGender = selectedVoice.gender;
      
      // Update avatar manager voice
      if (avatarManager) {
        avatarManager.setVoice(currentVoiceId);
        
        // Update animations if gender changed
        const newAnimationsUrl = getAnimationsUrl(currentAvatarGender);
        if (avatarManager.setAnimationsUrl) {
          avatarManager.setAnimationsUrl(newAnimationsUrl);
        }
      }
      
      // Update status
      updateAvatarStatus(`Voice changed to ${selectedVoice.name}`);
      setTimeout(() => {
        updateAvatarStatus('Ready to help');
      }, 2000);
      
      console.log('Voice changed to:', selectedVoice);
    }
    
    function isLikelyMaleAvatar(avatarUrl) {
      // Helper function to detect if an avatar might be male despite being detected as female
      // This is a simple heuristic - you could make this more sophisticated
      
      if (typeof avatarUrl === 'string') {
        // Check for male indicators in the URL or filename
        const urlLower = avatarUrl.toLowerCase();
        
        // Look for male-specific patterns
        if (urlLower.includes('beard') || urlLower.includes('mustache') || 
            urlLower.includes('masculine') || urlLower.includes('man') ||
            urlLower.includes('male') || urlLower.includes('boy')) {
          return true;
        }
        
        // For Ready Player Me avatars, we could check other heuristics
        // For now, let's show the button for all custom avatars to let user decide
        if (avatarUrl.includes('models.readyplayer.me') || avatarUrl.startsWith('blob:')) {
          return true; // Show option for all custom avatars
        }
      }
      
      return false;
    }
    
    function forceMaleAnimations() {
      if (!avatarManager) {
        console.error('Avatar manager not initialized');
        return;
      }
      
      // Force male gender and animations
      currentAvatarGender = 'male';
      const maleAnimationsUrl = getAnimationsUrl('male');
      
      updateAvatarStatus('🔄 Switching to male animations...');
      
      // Update animations
      if (avatarManager.setAnimationsUrl) {
        avatarManager.setAnimationsUrl(maleAnimationsUrl);
      } else if (avatarManager.loadAnimations) {
        avatarManager.loadAnimations(maleAnimationsUrl);
      }
      
      // Also suggest male voice
      const maleVoice = availableVoices.male;
      currentVoiceId = maleVoice.id;
      
      // Update avatar manager voice
      if (avatarManager.setVoice) {
        avatarManager.setVoice(currentVoiceId);
      }
      
      // Hide the force button (button removed from UI)
      // document.getElementById('force-male-animations-btn').style.display = 'none';
      
      updateAvatarStatus('✅ Switched to male animations and voice');
      setTimeout(() => {
        updateAvatarStatus('Ready to help');
      }, 2000);
      
      console.log('Forced male animations and voice');
    }
    
    function revertToOriginalAvatar() {
      if (!avatarManager) {
        console.error('Avatar manager not initialized');
        return;
      }
      
      updateAvatarStatus('🔄 Resetting to default avatar...');
      
      // Reset to default female avatar
      const defaultAvatar = availableAvatars.female;
      currentAvatarUrl = defaultAvatar.avatarUrl;
      currentAvatarGender = defaultAvatar.gender;
      
      // Reset voice to default female
      currentVoiceId = availableVoices.female.id;
      
      // Update circular button selections
      document.querySelectorAll('.avatar-circle-btn[data-avatar]').forEach(btn => {
        btn.classList.remove('active');
      });
      document.querySelector('.avatar-circle-btn[data-avatar="female"]').classList.add('active');
      
      // Check if avatar manager has a method to reload
      if (typeof avatarManager.loadNewAvatar === 'function') {
        avatarManager.loadNewAvatar(defaultAvatar.avatarUrl).then(() => {
          // Update voice and animations
          avatarManager.setVoice(currentVoiceId);
          if (avatarManager.setAnimationsUrl) {
            avatarManager.setAnimationsUrl(defaultAvatar.animationsUrl);
          }
          
          updateAvatarStatus('✅ Reset to default avatar');
          setTimeout(() => {
            updateAvatarStatus('Ready to help');
          }, 2000);
        }).catch(error => {
          console.error('Error reverting avatar:', error);
          updateAvatarStatus('❌ Error resetting avatar');
        });
      } else {
        // Fallback: reinitialize avatar manager
        const avatarContainer = document.getElementById('avatar-3d');
        avatarContainer.innerHTML = '';
        
        avatarManager = new AvatarManager('avatar-3d', {
          elevenlabsApiKey: 'sk_72283c30a844b3d198dda76a38373741c8968217a9472ae7',
          voice: currentVoiceId,
          avatarUrl: defaultAvatar.avatarUrl,
          animationsUrl: defaultAvatar.animationsUrl,
          volume: document.getElementById('avatar-volume-slider').value / 100
        });
        
        // Wait for reinitialization
        const checkReinitialization = setInterval(() => {
          if (avatarManager && avatarManager.isInitialized) {
            clearInterval(checkReinitialization);
            updateAvatarStatus('✅ Reset to default avatar');
            setTimeout(() => {
              updateAvatarStatus('Ready to help');
            }, 2000);
          }
        }, 500);
      }
    }
    
    function initializeAvatarCustomization() {
      // Set up Ready Player Me Avatar Creator
      const customizeBtn = document.getElementById('customize-avatar-btn');
      const modal = document.getElementById('avatar-creator-modal');
      const frame = document.getElementById('avatar-creator-frame');
      
      // Ready Player Me configuration
      const subdomain = 'demo'; // You can replace with your custom subdomain
      frame.src = `https://${subdomain}.readyplayer.me/avatar?frameApi`;
      
      // Event listeners for Ready Player Me
      window.addEventListener('message', handleReadyPlayerMeMessage);
      document.addEventListener('message', handleReadyPlayerMeMessage);
      
      // Close modal when clicking outside
      modal.addEventListener('click', function(e) {
        if (e.target === modal) {
          closeAvatarCreator();
        }
      });
      
      // Prevent closing when clicking inside modal content
      modal.querySelector('.rpm-modal-content').addEventListener('click', function(e) {
        e.stopPropagation();
      });
    }
    
    function handleReadyPlayerMeMessage(event) {
      const json = parseMessage(event);
      
      if (json?.source !== 'readyplayerme') {
        return;
      }
      
      console.log('Ready Player Me event:', json);
      
      // Subscribe to all events when frame is ready
      if (json.eventName === 'v1.frame.ready') {
        const frame = document.getElementById('avatar-creator-frame');
        frame.contentWindow.postMessage(
          JSON.stringify({
            target: 'readyplayerme',
            type: 'subscribe',
            eventName: 'v1.**'
          }),
          '*'
        );
        console.log('Subscribed to Ready Player Me events');
      }
      
      // Handle avatar export
      if (json.eventName === 'v1.avatar.exported') {
        const avatarUrl = json.data.url;
        console.log('New avatar URL:', avatarUrl);
        
        // Show loading feedback
        updateAvatarStatus('Processing your new avatar...');
        
        // Update the avatar in the 3D viewer
        updateAvatarModel(avatarUrl);
        
        // Close the modal
        closeAvatarCreator();
      }
      
      // Handle user ID
      if (json.eventName === 'v1.user.set') {
        console.log('User ID set:', json.data.id);
      }
    }
    
    function parseMessage(event) {
      try {
        return JSON.parse(event.data);
      } catch (error) {
        return null;
      }
    }
    
    function openAvatarCreator() {
      const modal = document.getElementById('avatar-creator-modal');
      const iframe = document.getElementById('avatar-creator-frame');
      
      // Set the Ready Player Me URL if not already set
      if (!iframe.src) {
        iframe.src = 'https://verztech.readyplayer.me/avatar?frameApi';
      }
      
      modal.style.display = 'block';
      document.body.style.overflow = 'hidden'; // Prevent background scrolling
      updateAvatarStatus('🎨 Opening avatar creator...');
    }
    
    function closeAvatarCreator() {
      const modal = document.getElementById('avatar-creator-modal');
      modal.style.display = 'none';
      document.body.style.overflow = 'auto'; // Restore scrolling
    }
    
    function updateAvatarModel(avatarUrl) {
      if (!avatarManager) {
        console.error('Avatar manager not initialized');
        return;
      }
      
      // Extract avatar ID from the URL
      const avatarId = extractAvatarId(avatarUrl);
      if (!avatarId) {
        console.error('Could not extract avatar ID from URL:', avatarUrl);
        return;
      }
      
      // Update current avatar URL
      currentAvatarUrl = avatarUrl;
      
      // Detect gender (basic implementation)
      currentAvatarGender = detectAvatarGender(avatarUrl);
      
      // Create the enhanced URL with morph targets for facial expressions
      const enhancedAvatarUrl = `https://models.readyplayer.me/${avatarId}.glb?morphTargets=ARKit,Oculus Visemes&quality=medium`;
      
      console.log('Downloading avatar with morph targets:', enhancedAvatarUrl);
      console.log('Detected gender:', currentAvatarGender);
      
      // Update the status
      updateAvatarStatus('🔄 Downloading avatar with facial expressions...');
      
      // Download the avatar and store it locally
      downloadAndStoreAvatar(enhancedAvatarUrl, avatarId).then(localAvatarUrl => {
        console.log('Avatar downloaded successfully:', localAvatarUrl);
        
        // Update status
        updateAvatarStatus('Loading avatar...');
        
        // Get appropriate animations based on gender
        const animationsUrl = getAnimationsUrl(currentAvatarGender);
        
        // Now load the avatar with morph targets
        if (typeof avatarManager.loadNewAvatar === 'function') {
          avatarManager.loadNewAvatar(localAvatarUrl).then(() => {
            // Update animations for gender-specific movements
            const appropriateAnimationsUrl = getAnimationsUrl(currentAvatarGender);
            console.log('Loading animations for gender:', currentAvatarGender, 'URL:', appropriateAnimationsUrl);
            
            if (avatarManager.setAnimationsUrl) {
              avatarManager.setAnimationsUrl(appropriateAnimationsUrl);
            } else if (avatarManager.loadAnimations) {
              avatarManager.loadAnimations(appropriateAnimationsUrl);
            }
            
            updateAvatarStatus('✅ Avatar updated with facial expressions!');
            
            // Show force male animations button if avatar might be male but detected as female
            if (currentAvatarGender === 'female' && isLikelyMaleAvatar(localAvatarUrl)) {
              document.getElementById('force-male-animations-btn').style.display = 'inline-block';
              updateAvatarStatus('💡 Avatar detected as female. Click "Use Male Animations" if this is a male avatar.');
              setTimeout(() => {
                updateAvatarStatus('Ready to help');
              }, 5000);
            } else {
              document.getElementById('force-male-animations-btn').style.display = 'none';
              // Suggest voice change if appropriate
              setTimeout(() => {
                suggestVoiceChange(currentAvatarGender);
              }, 2000);
            }
            
            setTimeout(() => {
              updateAvatarStatus('Ready to help');
            }, 3000);
          }).catch(error => {
            console.error('Error loading new avatar:', error);
            updateAvatarStatus('❌ Error loading avatar');
            setTimeout(() => {
              updateAvatarStatus('Ready to help');
            }, 3000);
          });
        } else {
          // Fallback: reinitialize with new URL
          const avatarContainer = document.getElementById('avatar-3d');
          avatarContainer.innerHTML = ''; // Clear existing avatar
          
          // Get appropriate animations based on gender
          const appropriateAnimationsUrl = getAnimationsUrl(currentAvatarGender);
          console.log('Reinitializing with gender:', currentAvatarGender, 'animations:', appropriateAnimationsUrl);
          
          // Reinitialize avatar manager with new URL and appropriate animations
          avatarManager = new AvatarManager('avatar-3d', {
            elevenlabsApiKey: 'sk_72283c30a844b3d198dda76a38373741c8968217a9472ae7',
            voice: currentVoiceId,
            avatarUrl: localAvatarUrl,
            animationsUrl: appropriateAnimationsUrl,
            volume: document.getElementById('avatar-volume-slider').value / 100
          });
          
          // Wait for reinitialization
          const checkReinitialization = setInterval(() => {
            if (avatarManager && avatarManager.isInitialized) {
              clearInterval(checkReinitialization);
              updateAvatarStatus('✅ Avatar updated with facial expressions!');
              
              // Show force male animations button if needed
              if (currentAvatarGender === 'female' && isLikelyMaleAvatar(localAvatarUrl)) {
                document.getElementById('force-male-animations-btn').style.display = 'inline-block';
                updateAvatarStatus('💡 Avatar detected as female. Click "Use Male Animations" if this is a male avatar.');
                setTimeout(() => {
                  updateAvatarStatus('Ready to help');
                }, 5000);
              } else {
                document.getElementById('force-male-animations-btn').style.display = 'none';
                // Suggest voice change if appropriate
                setTimeout(() => {
                  suggestVoiceChange(currentAvatarGender);
                }, 2000);
              }
              
              setTimeout(() => {
                updateAvatarStatus('Ready to help');
              }, 3000);
            }
          }, 500);
          
          // Timeout for reinitialization
          setTimeout(() => {
            clearInterval(checkReinitialization);
            if (!avatarManager || !avatarManager.isInitialized) {
              updateAvatarStatus('❌ Avatar loading timeout');
              setTimeout(() => {
                updateAvatarStatus('Ready to help');
              }, 2000);
            }
          }, 15000);
        }
      }).catch(error => {
        console.error('Error downloading avatar:', error);
        updateAvatarStatus('❌ Error downloading avatar - please try again');
        setTimeout(() => {
          updateAvatarStatus('Ready to help');
        }, 4000);
      });
    }
    
    function extractAvatarId(avatarUrl) {
      // Ready Player Me URLs are in format: https://models.readyplayer.me/AVATAR_ID.glb
      const match = avatarUrl.match(/\/([a-f0-9]{24})\.glb$/);
      return match ? match[1] : null;
    }
    
    async function downloadAndStoreAvatar(avatarUrl, avatarId) {
      try {
        // Use the PHP script to download and store the avatar
        const response = await fetch('download_avatar.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            avatarUrl: avatarUrl,
            avatarId: avatarId
          })
        });
        
        if (!response.ok) {
          throw new Error(`Server error: ${response.status}`);
        }
        
        const result = await response.json();
        
        if (!result.success) {
          throw new Error(result.error || 'Failed to download avatar');
        }
        
        console.log('Avatar downloaded successfully:', result);
        console.log('File size:', result.fileSize, 'bytes');
        
        // Return the local URL for the avatar
        return result.localUrl;
        
      } catch (error) {
        console.error('Error downloading avatar:', error);
        
        // Fallback: try to download directly in the browser
        console.log('Attempting direct download fallback...');
        try {
          const directResponse = await fetch(`https://models.readyplayer.me/${avatarId}.glb?morphTargets=ARKit,Oculus Visemes&quality=medium`);
          if (!directResponse.ok) {
            throw new Error(`Direct download failed: ${directResponse.status}`);
          }
          
          const avatarBlob = await directResponse.blob();
          const localAvatarUrl = URL.createObjectURL(avatarBlob);
          
          console.log('Direct download successful');
          return localAvatarUrl;
          
        } catch (directError) {
          console.error('Direct download also failed:', directError);
          throw error;
        }
      }
    }
    
    // --- Gender and Voice Selection Modal Logic ---
let pendingCustomAvatarUrl = null;
let selectedGender = null;
let selectedVoiceId = null;

function showGenderSelectionModal(avatarUrl) {
  pendingCustomAvatarUrl = avatarUrl;
  selectedGender = null;
  document.querySelectorAll('.gender-btn').forEach(btn => btn.classList.remove('selected'));
  document.getElementById('confirm-gender-btn').disabled = true;
  document.getElementById('gender-selection-modal').classList.add('show');
}

function selectGender(gender) {
  selectedGender = gender;
  document.querySelectorAll('.gender-btn').forEach(btn => {
    btn.classList.toggle('selected', btn.getAttribute('data-gender') === gender);
  });
  document.getElementById('confirm-gender-btn').disabled = false;
}

function confirmGender() {
  document.getElementById('gender-selection-modal').classList.remove('show');
  showVoiceSelectionModal(selectedGender);
}

function cancelAvatarCustomization() {
  document.getElementById('gender-selection-modal').classList.remove('show');
  pendingCustomAvatarUrl = null;
  selectedGender = null;
}

function showVoiceSelectionModal(gender) {
  // Filter voices by gender
  const voices = Object.values(availableVoices).filter(v => v.gender === gender);
  const container = document.getElementById('voice-options-container');
  container.innerHTML = '';
  voices.forEach(voice => {
    const div = document.createElement('div');
    div.className = 'voice-option';
    div.setAttribute('data-voice-id', voice.id);
    div.innerHTML = `
      <div class="voice-option-info">
        <i class="fa fa-${gender === 'female' ? 'female' : 'male'}"></i>
        <span class="voice-option-name">${voice.name}</span>
      </div>
      <button class="voice-preview-btn" onclick="event.stopPropagation(); previewVoice('${voice.id}', '${voice.name}')">Preview</button>
    `;
    div.onclick = function() {
      document.querySelectorAll('.voice-option').forEach(opt => opt.classList.remove('selected'));
      div.classList.add('selected');
      selectedVoiceId = voice.id;
      document.getElementById('confirm-voice-btn').disabled = false;
    };
    container.appendChild(div);
  });
  selectedVoiceId = null;
  document.getElementById('confirm-voice-btn').disabled = true;
  document.getElementById('voice-preview-status').textContent = 'Click "Preview" to hear how each voice sounds';
  document.getElementById('voice-selection-modal').classList.add('show');
}

function previewVoice(voiceId, voiceName) {
  const statusElement = document.getElementById('voice-preview-status');
  const dropdownStatusElement = document.getElementById('voice-dropdown-status');
  const previewBtn = event.target;
  
  // Find the voice configuration that matches the voiceId
  let previewFile = null;
  for (const [key, voice] of Object.entries(availableVoices)) {
    if (voice.id === voiceId) {
      previewFile = voice.previewFile;
      break;
    }
  }
  
  if (!previewFile) {
    const errorMsg = `No preview available for ${voiceName}`;
    if (statusElement) statusElement.textContent = errorMsg;
    if (dropdownStatusElement) {
      dropdownStatusElement.textContent = errorMsg;
      dropdownStatusElement.style.display = 'block';
    }
    console.error('No preview file found for voice:', voiceId);
    return;
  }
  
  // Disable the button during preview
  previewBtn.disabled = true;
  previewBtn.textContent = 'Playing...';
  
  // Update status elements
  const playingMsg = `Playing preview for ${voiceName}...`;
  if (statusElement) statusElement.textContent = playingMsg;
  if (dropdownStatusElement) {
    dropdownStatusElement.textContent = playingMsg;
    dropdownStatusElement.style.display = 'block';
  }
  
  // Create and play audio from local file
  const audio = new Audio(previewFile);
  
  audio.onloadstart = () => {
    console.log('Loading preview audio for:', voiceName);
  };
  
  audio.oncanplaythrough = () => {
    console.log('Audio ready to play for:', voiceName);
  };
  
  audio.onended = () => {
    const finishedMsg = `Preview finished for ${voiceName}`;
    if (statusElement) statusElement.textContent = finishedMsg;
    if (dropdownStatusElement) {
      dropdownStatusElement.textContent = finishedMsg;
      setTimeout(() => {
        dropdownStatusElement.style.display = 'none';
      }, 2000);
    }
    previewBtn.disabled = false;
    previewBtn.textContent = 'Play';
    console.log('Audio finished playing for:', voiceName);
  };
  
  audio.onerror = (error) => {
    console.error('Audio error for:', voiceName, error);
    const errorMsg = `Error playing preview for ${voiceName}`;
    if (statusElement) statusElement.textContent = errorMsg;
    if (dropdownStatusElement) {
      dropdownStatusElement.textContent = errorMsg;
      dropdownStatusElement.style.display = 'block';
    }
    previewBtn.disabled = false;
    previewBtn.textContent = 'Play';
  };
  
  audio.onabort = () => {
    console.log('Audio playback aborted for:', voiceName);
    const stoppedMsg = `Preview stopped for ${voiceName}`;
    if (statusElement) statusElement.textContent = stoppedMsg;
    if (dropdownStatusElement) {
      dropdownStatusElement.textContent = stoppedMsg;
      dropdownStatusElement.style.display = 'block';
    }
    previewBtn.disabled = false;
    previewBtn.textContent = 'Play';
  };
  
  // Play the audio
  audio.play().catch(err => {
    console.error('Audio play error for:', voiceName, err);
    const errorMsg = `Error playing preview for ${voiceName}`;
    if (statusElement) statusElement.textContent = errorMsg;
    if (dropdownStatusElement) {
      dropdownStatusElement.textContent = errorMsg;
      dropdownStatusElement.style.display = 'block';
    }
    previewBtn.disabled = false;
    previewBtn.textContent = 'Play';
  });
}

function confirmVoiceSelection() {
  document.getElementById('voice-selection-modal').classList.remove('show');
  if (pendingCustomAvatarUrl && selectedGender && selectedVoiceId) {
    // Now initialize the avatar with the selected gender and voice
    initializeCustomAvatarWithGenderAndVoice(pendingCustomAvatarUrl, selectedGender, selectedVoiceId);
    pendingCustomAvatarUrl = null;
    selectedGender = null;
    selectedVoiceId = null;
  }
}

function goBackToGenderSelection() {
  document.getElementById('voice-selection-modal').classList.remove('show');
  showGenderSelectionModal(pendingCustomAvatarUrl);
}

function initializeCustomAvatarWithGenderAndVoice(avatarUrl, gender, voiceId) {
  // Set global gender/voice
  currentAvatarGender = gender;
  currentVoiceId = voiceId;
  // Continue with the normal avatar initialization flow
  updateAvatarModelWithGenderAndVoice(avatarUrl, gender, voiceId);
}

function updateAvatarModelWithGenderAndVoice(avatarUrl, gender, voiceId) {
  // This is a copy of updateAvatarModel, but uses the provided gender/voice
  if (!avatarManager) {
    console.error('Avatar manager not initialized');
    return;
  }
  const avatarId = extractAvatarId(avatarUrl);
  if (!avatarId) {
    console.error('Could not extract avatar ID from URL:', avatarUrl);
    return;
  }
  
  currentAvatarUrl = avatarUrl;
  currentAvatarGender = gender;
  currentVoiceId = voiceId;
  
  // Create the enhanced URL with morph targets for facial expressions
  const enhancedAvatarUrl = `https://models.readyplayer.me/${avatarId}.glb?morphTargets=ARKit,Oculus Visemes&quality=medium`;
  
  console.log('Downloading avatar with morph targets:', enhancedAvatarUrl);
  console.log('Selected gender:', gender);
  console.log('Selected voice:', voiceId);
  
  updateAvatarStatus('🔄 Loading custom avatar...');
  
  downloadAndStoreAvatar(enhancedAvatarUrl, avatarId).then(localAvatarUrl => {
    console.log('Avatar downloaded successfully:', localAvatarUrl);
    
    updateAvatarStatus('Loading custom avatar...');
    
    const animationsUrl = getAnimationsUrl(gender);
    
    if (typeof avatarManager.loadNewAvatar === 'function') {
      avatarManager.loadNewAvatar(localAvatarUrl).then(() => {
        // Update voice after avatar is loaded
        if (avatarManager.setVoice) {
          avatarManager.setVoice(voiceId);
        }
        
        // Update animations for gender-specific movements
        if (avatarManager.setAnimationsUrl) {
          avatarManager.setAnimationsUrl(animationsUrl);
        } else if (avatarManager.loadAnimations) {
          avatarManager.loadAnimations(animationsUrl);
        }
        
        updateAvatarStatus('✅ Custom avatar loaded with facial expressions!');
        
        // Update UI elements
        updateVoiceDisplay(voiceId);
        updateAvatarDisplay(gender);
        
        setTimeout(() => { updateAvatarStatus('Ready to help'); }, 3000);
      }).catch(error => {
        console.error('Error loading new avatar:', error);
        updateAvatarStatus('Avatar Loaded');
        setTimeout(() => { updateAvatarStatus('Ready to help'); }, 3000);
      });
    } else {
      // Fallback: reinitialize
      const avatarContainer = document.getElementById('avatar-3d');
      avatarContainer.innerHTML = '';
      
      avatarManager = new AvatarManager('avatar-3d', {
        elevenlabsApiKey: 'sk_72283c30a844b3d198dda76a38373741c8968217a9472ae7',
        voice: voiceId,
        avatarUrl: localAvatarUrl,
        animationsUrl: animationsUrl,
        volume: document.getElementById('avatar-volume-slider').value / 100
      });
      
      const checkReinit = setInterval(() => {
        if (avatarManager && avatarManager.isInitialized) {
          clearInterval(checkReinit);
          updateAvatarStatus('✅ Custom avatar loaded with facial expressions!');
          
          // Update UI elements
          updateVoiceDisplay(voiceId);
          updateAvatarDisplay(gender);
          
          setTimeout(() => { updateAvatarStatus('Ready to help'); }, 3000);
        }
      }, 500);
    }
  }).catch(error => {
    console.error('Error downloading avatar:', error);
    updateAvatarStatus('❌ Error downloading custom avatar');
    setTimeout(() => { updateAvatarStatus('Ready to help'); }, 3000);
  });
}

function updateVoiceDisplay(voiceId) {
  // Find the voice name by ID
  const voice = Object.values(availableVoices).find(v => v.id === voiceId);
  if (voice) {
    // Update the avatar manager's voice if it exists
    if (avatarManager && avatarManager.setVoice) {
      avatarManager.setVoice(voiceId);
    }
  }
}

function updateAvatarDisplay(gender) {
  // Update the avatar display to show "Custom Avatar"
  document.getElementById('current-avatar-name').textContent = `Custom Avatar (${gender === 'female' ? 'Female' : 'Male'})`;
  
  // Update the avatar selection to show custom avatar as active
  document.querySelectorAll('.avatar-menu-item').forEach(item => {
    item.classList.remove('active');
  });
  
  // Add a custom avatar option to the dropdown if it doesn't exist
  const avatarMenu = document.getElementById('avatar-selection-menu');
  if (avatarMenu && !avatarMenu.querySelector('.avatar-menu-item[data-avatar="custom"]')) {
    const customOption = document.createElement('div');
    customOption.className = 'avatar-menu-item active';
    customOption.setAttribute('data-avatar', 'custom');
    customOption.innerHTML = `
      <i class="fa fa-user"></i>
      <span>Custom Avatar</span>
      <i class="fa fa-check avatar-checkmark"></i>
    `;
    avatarMenu.appendChild(customOption);
  } else if (avatarMenu) {
    // Make the custom option active
    const customOption = avatarMenu.querySelector('.avatar-menu-item[data-avatar="custom"]');
    if (customOption) {
      customOption.classList.add('active');
    }
  }
}

// --- Patch Ready Player Me export handler to show gender/voice modals ---
function handleReadyPlayerMeMessage(event) {
  const json = parseMessage(event);
  if (json?.source !== 'readyplayerme') return;
  
  console.log('Ready Player Me event:', json);
  
  // Subscribe to all events when frame is ready
  if (json.eventName === 'v1.frame.ready') {
    const frame = document.getElementById('avatar-creator-frame');
    frame.contentWindow.postMessage(
      JSON.stringify({
        target: 'readyplayerme',
        type: 'subscribe',
        eventName: 'v1.**'
      }),
      '*'
    );
    console.log('Subscribed to Ready Player Me events');
  }
  
  // Handle avatar export - show gender/voice selection instead of immediate initialization
  if (json.eventName === 'v1.avatar.exported') {
    const avatarUrl = json.data.url;
    console.log('New avatar URL:', avatarUrl);
    
    // Close the Ready Player Me modal first
    closeAvatarCreator();
    
    // Show gender selection modal
    showGenderSelectionModal(avatarUrl);
    return;
  }
  
  // Handle user ID
  if (json.eventName === 'v1.user.set') {
    console.log('User ID set:', json.data.id);
  }
}
  </script>

  <!-- Ready Player Me Avatar Creator Modal -->
  <div id="avatar-creator-modal" class="rpm-modal" style="display: none;">
    <div class="rpm-modal-content">
      <div class="rpm-modal-header">
        <h3>Customize Your Avatar</h3>
        <button class="rpm-close-btn" onclick="closeAvatarCreator()">
          <i class="fa fa-times"></i>
        </button>
      </div>
      <div class="rpm-modal-body">
        <iframe id="avatar-creator-frame" class="rpm-frame" allow="camera *; microphone *; clipboard-write"></iframe>
      </div>
    </div>
  </div>

  <!-- Gender and Voice Selection Modals (New) -->
  <div id="gender-selection-modal" class="avatar-selection-modal">
    <div class="avatar-selection-content">
      <h3>🎭 Character Gender</h3>
      <p>Please select the gender of your customized avatar to ensure proper animations and voice matching.</p>
      
      <div class="gender-selection-buttons">
        <button class="gender-btn" data-gender="female" onclick="selectGender('female')">
          <i class="fa fa-female"></i>
          <span>Feminine</span>
        </button>
        <button class="gender-btn" data-gender="male" onclick="selectGender('male')">
          <i class="fa fa-male"></i>
          <span>Masculine</span>
        </button>
      </div>
      
      <div class="selection-modal-buttons">
        <button class="selection-btn primary" id="confirm-gender-btn" onclick="confirmGender()" disabled>
          Continue
        </button>
        <button class="selection-btn secondary" onclick="cancelAvatarCustomization()">
          Cancel
        </button>
      </div>
    </div>
  </div>
  
  <div id="voice-selection-modal" class="avatar-selection-modal">
    <div class="avatar-selection-content">
      <h3>🎙️ Voice Selection</h3>
      <p>Choose a voice for your avatar and preview how it sounds.</p>
      
      <div class="voice-selection-container">
        <div class="voice-options" id="voice-options-container">
          <!-- Voice options will be populated by JavaScript -->
        </div>
        
        <div class="preview-status" id="voice-preview-status">
          Click "Preview" to hear how each voice sounds
        </div>
      </div>
      
      <div class="selection-modal-buttons">
        <button class="selection-btn primary" id="confirm-voice-btn" onclick="confirmVoiceSelection()" disabled>
          Apply Settings
        </button>
        <button class="selection-btn secondary" onclick="goBackToGenderSelection()">
          Back
        </button>
      </div>
    </div>
  </div>

  <script src="js/jquery-3.4.1.min.js"></script>
  <script src="js/bootstrap.bundle.min.js"></script>
  <script src="js/scripts.js"></script>

<!-- Sidebar for History -->
<div id="chat-history-sidebar" style="
  position: fixed;
  top: 0;
  right: -350px;
  width: 350px;
  height: 100%;
  background: #fff;
  box-shadow: -4px 0 12px rgba(0,0,0,0.2);
  z-index: 999;
  padding: 20px;
  overflow-y: auto;
  transition: right 0.3s ease;
">
  <button onclick="document.getElementById('chat-history-sidebar').style.right='-350px'" style="
    position: absolute;
    top: 10px;
    right: 10px;
    background: #000;
    color: #fff;
    border: none;
    padding: 5px 10px;
    cursor: pointer;
    font-size: 14px;
    border-radius: 4px;
  ">X</button>
  <h5>Chat History</h5>
  <div id="chat-history-list">
    <p>Loading...</p>
  </div>
</div>

<script>
  document.addEventListener("DOMContentLoaded", function () {
    const historySidebar = document.getElementById('chat-history-sidebar');
    const historyList = document.getElementById('chat-history-list');
    const historyButton = document.getElementById('history-button');

    if (historyButton) {
      historyButton.addEventListener('click', () => {
        if (historySidebar.style.right === '0px') {
          historySidebar.style.right = '-350px';
        } else {
          historySidebar.style.right = '0px';
          fetchChatHistory();
        }
      });
    }

    function fetchChatHistory() {
      fetch('fetch_chat_history.php')
        .then(response => response.json())
        .then(data => {
          if (data.error) {
            historyList.innerHTML = `<p>${data.error}</p>`;
            return;
          }

          if (data.length === 0) {
            historyList.innerHTML = '<p>No chat history yet.</p>';
            return;
          }

          historyList.innerHTML = '';
          data.forEach(entry => {
            const item = document.createElement('div');
            item.style.marginBottom = '15px';
            item.innerHTML = `
              <strong>You:</strong> ${entry.question}<br>
              <strong>Bot:</strong> ${entry.answer}<br>
              <small>${new Date(entry.timestamp).toLocaleString()}</small>
              <hr>
            `;
            historyList.appendChild(item);
          });
        })
        .catch(err => {
          console.error('Error fetching chat history:', err);
          historyList.innerHTML = '<p>Error loading history.</p>';
        });
    }
  });
</script>

<style>
  .loading-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    z-index: 9999;
    display: flex;
    justify-content: center;
    align-items: center;
    display: none;
  }
  .loading-spinner {
    width: 50px;
    height: 50px;
    border: 5px solid white;
    border-top: 5px solid #fbbf24;
    border-radius: 50%;
    animation: spin 1s linear infinite;
  }
  @keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
  }
  .avatar-button {
    background-color: white !important;
    color: black !important;
    border: 2px solid white;
    transition: background-color 0.3s, color 0.3s;
  }
  .avatar-button.active {
    background-color: #007bff !important;
    color: white !important;
    border-color: #007bff;
  }
</style>
<div id="avatar-loading-overlay" class="loading-overlay">
  <div class="loading-spinner"></div>
</div>

<script>
  function showAvatarLoading() {
    const overlay = document.getElementById('avatar-loading-overlay');
    overlay.style.display = 'flex';
  }

  function hideAvatarLoading() {
    const overlay = document.getElementById('avatar-loading-overlay');
    overlay.style.display = 'none';
  }

  function switchAvatarWithLoading(newAvatar) {
    showAvatarLoading();
    loadAvatar(newAvatar, () => {
      setTimeout(() => {
        hideAvatarLoading();
      }, 2000);
    });
  }

  function loadAvatar(avatarName, callback) {
    const iframe = document.getElementById('avatarIframe');
    iframe.src = `/avatars/${avatarName}.html`;
    iframe.onload = callback;
  }

  document.querySelectorAll('.avatar-button').forEach(button => {
    button.addEventListener('click', () => {
      document.querySelectorAll('.avatar-button').forEach(b => b.classList.remove('active'));
      button.classList.add('active');
    });
  });
</script>

</body>
</html>
