// JDTIS Asset Management System - Main JavaScript

document.addEventListener('DOMContentLoaded', function() {
    console.log('JDTIS Asset Management System loaded');
});

// Fungsi untuk konfirmasi hapus
function confirmDelete(id) {
    if (confirm('Adakah anda pasti ingin memadamkan?')) {
        window.location.href = 'hapus.php?id=' + id;
    }
}

// Fungsi untuk validasi form
function validateForm() {
    let isValid = true;
    const inputs = document.querySelectorAll('input[required], textarea[required]');
    
    inputs.forEach(input => {
        if (input.value.trim() === '') {
            input.style.borderColor = 'red';
            isValid = false;
        } else {
            input.style.borderColor = '';
        }
    });
    
    return isValid;
}
