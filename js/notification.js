function parseTimestampAsUTC(dateStr) {
  // Add 'Z' to treat input as UTC time string
  return new Date(dateStr + "Z");
}

function showNotificationPopup(announcement) {
  // Only show if not already dismissed
  if (document.querySelector('.custom-alert')) return;

  const alert = document.createElement('div');
  alert.className = 'custom-alert';
  alert.style.cssText = `
    position: fixed;
    top: 20px;
    right: 20px;
    background: #fff;
    color: #000;
    border-left: 6px solid #2a4d9c;
    padding: 1.25rem 1.5rem;
    box-shadow: 0 6px 12px rgba(0, 0, 0, 0.25);
    z-index: 9999;
    border-radius: 10px;
    max-width: 420px;
    width: 90%;
    font-size: 1rem;
    cursor: pointer;
  `;
  alert.innerHTML = `
    <strong>New Announcement:</strong><br>
    <span style="font-size: 1.1rem; font-weight: 600;">${announcement.title}</span><br>
    <small style="color:gray;">Click to view</small>
  `;

  alert.onclick = function () {
    document.getElementById('modalTitle').textContent = announcement.title;
    document.getElementById('modalContent').innerHTML = announcement.context;
    document.getElementById('modalAudience').textContent = announcement.target_audience;
    document.getElementById('modalPriority').textContent = announcement.priority;

    const options = {
      timeZone: 'Asia/Singapore',
      year: 'numeric',
      month: 'short',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
      hour12: true,
    };

    const utcDate = parseTimestampAsUTC(announcement.timestamp);
    const sgTime = utcDate.toLocaleString('en-SG', options);
    document.getElementById('modalTimestamp').textContent = sgTime;

    const modal = new bootstrap.Modal(document.getElementById('announcementModal'));
    modal.show();
    alert.remove();
    localStorage.setItem('lastSeenAnnouncement', announcement.timestamp);
  };

  document.body.appendChild(alert);

  setTimeout(() => {
    if (document.body.contains(alert)) {
      alert.remove();
      localStorage.setItem('lastSeenAnnouncement', announcement.timestamp);
    }
  }, 10000);
}

setInterval(() => {
  fetch('/admin/latest_announcement.php')
    .then(res => res.json())
    .then(announcement => {
      if (!announcement) return;
      const lastSeen = localStorage.getItem('lastSeenAnnouncement') || '';
      if (new Date(announcement.timestamp) > new Date(lastSeen)) {
        showNotificationPopup(announcement);
      }
    });
}, 5000); // every 5 seconds
