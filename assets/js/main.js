// PeakMiles - Main JavaScript

document.addEventListener('DOMContentLoaded', function() {
  // FAQ Toggle
  document.querySelectorAll('.faq-question').forEach(btn => {
    btn.addEventListener('click', function() {
      const item = this.closest('.faq-item');
      const isOpen = item.classList.contains('open');
      document.querySelectorAll('.faq-item').forEach(i => i.classList.remove('open'));
      if (!isOpen) item.classList.add('open');
    });
  });

  // Animate progress bars
  document.querySelectorAll('.progress-bar-fill').forEach(bar => {
    const target = bar.dataset.width || '0';
    bar.style.width = '0%';
    setTimeout(() => { bar.style.width = target + '%'; }, 300);
  });

  // Smooth scroll for anchor links
  document.querySelectorAll('a[href^="#"]').forEach(a => {
    a.addEventListener('click', function(e) {
      const target = document.querySelector(this.getAttribute('href'));
      if (target) {
        e.preventDefault();
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    });
  });

  // Navbar scroll effect
  const navbar = document.querySelector('.navbar');
  if (navbar) {
    window.addEventListener('scroll', () => {
      navbar.style.background = window.scrollY > 50 
        ? 'rgba(15,15,15,0.98)' 
        : 'rgba(15,15,15,0.95)';
    });
  }

  // Mobile sidebar toggle
  const sidebarToggle = document.getElementById('sidebarToggle');
  const sidebar = document.getElementById('sidebar');
  if (sidebarToggle && sidebar) {
    sidebarToggle.addEventListener('click', () => sidebar.classList.toggle('open'));
    document.addEventListener('click', (e) => {
      if (sidebar.classList.contains('open') && !sidebar.contains(e.target) && e.target !== sidebarToggle) {
        sidebar.classList.remove('open');
      }
    });
  }

  // File upload drag & drop
  const uploadAreas = document.querySelectorAll('.file-upload-area');
  uploadAreas.forEach(area => {
    const input = area.querySelector('input[type="file"]');
    area.addEventListener('dragover', (e) => { e.preventDefault(); area.classList.add('dragover'); });
    area.addEventListener('dragleave', () => area.classList.remove('dragover'));
    area.addEventListener('drop', (e) => {
      e.preventDefault();
      area.classList.remove('dragover');
      if (input && e.dataTransfer.files.length) {
        input.files = e.dataTransfer.files;
        updateFilePreview(input);
      }
    });
    area.addEventListener('click', () => input && input.click());
    if (input) {
      input.addEventListener('change', () => updateFilePreview(input));
    }
  });
});

function updateFilePreview(input) {
  if (!input.files || !input.files[0]) return;
  const file = input.files[0];
  const area = input.closest('.file-upload-area');
  if (!area) return;
  const preview = area.querySelector('.file-preview') || document.createElement('div');
  preview.className = 'file-preview';
  
  if (file.type.startsWith('image/')) {
    const reader = new FileReader();
    reader.onload = (e) => {
      preview.innerHTML = `<img src="${e.target.result}" style="max-width:100%;max-height:150px;border-radius:8px;margin-top:12px;">
        <div style="font-size:12px;color:var(--gray-light);margin-top:6px;">${file.name} (${(file.size/1024/1024).toFixed(2)} MB)</div>`;
    };
    reader.readAsDataURL(file);
  } else {
    preview.innerHTML = `<div style="font-size:13px;color:var(--primary);margin-top:8px;"><i class="fa fa-file"></i> ${file.name}</div>`;
  }
  
  if (!area.querySelector('.file-preview')) area.appendChild(preview);
}

function openModal(id) {
  const modal = document.getElementById(id);
  if (modal) modal.classList.add('active');
  document.body.style.overflow = 'hidden';
}

function closeModal(id) {
  const modal = document.getElementById(id);
  if (modal) modal.classList.remove('active');
  document.body.style.overflow = '';
}

function openLightbox(src) {
  document.getElementById('lightbox-img').src = src;
  document.getElementById('lightbox').classList.add('active');
  document.body.style.overflow = 'hidden';
}

function closeLightbox() {
  document.getElementById('lightbox').classList.remove('active');
  document.body.style.overflow = '';
}

document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') {
    closeLightbox();
    document.querySelectorAll('.modal-overlay.active').forEach(m => m.classList.remove('active'));
    document.body.style.overflow = '';
  }
});

// Close modal on overlay click
document.querySelectorAll('.modal-overlay').forEach(overlay => {
  overlay.addEventListener('click', function(e) {
    if (e.target === this) {
      this.classList.remove('active');
      document.body.style.overflow = '';
    }
  });
});

// Confirm dialog
function confirmAction(message, callback) {
  if (confirm(message)) callback();
}

// Number counter animation
function animateCounter(el) {
  const target = parseInt(el.dataset.target || el.textContent);
  let current = 0;
  const step = Math.ceil(target / 40);
  const timer = setInterval(() => {
    current = Math.min(current + step, target);
    el.textContent = current.toLocaleString('id-ID');
    if (current >= target) clearInterval(timer);
  }, 30);
}

// Intersection observer for animations
const observer = new IntersectionObserver((entries) => {
  entries.forEach(entry => {
    if (entry.isIntersecting) {
      entry.target.classList.add('in-view');
      if (entry.target.classList.contains('counter')) {
        animateCounter(entry.target);
      }
    }
  });
}, { threshold: 0.1 });

document.querySelectorAll('.animate-on-scroll, .counter').forEach(el => observer.observe(el));
