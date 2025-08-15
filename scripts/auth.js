const SUPABASE_URL = "https://bvlyzxljieftbkzkdwzv.supabase.co";
const SUPABASE_KEY = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImJ2bHl6eGxqaWVmdGJremtkd3p2Iiwicm9sZSI6ImFub24iLCJpYXQiOjE3NTA4ODQxNTgsImV4cCI6MjA2NjQ2MDE1OH0.mJEavNb2WC_0pBpg8KJq0ABc2hquYTewoge38U5P7dw";

const supabaseClient = supabase.createClient(SUPABASE_URL, SUPABASE_KEY);

// === GİRİŞ ===
document.getElementById('login-form')?.addEventListener('submit', async function(event) {
  event.preventDefault();
  const email = document.getElementById('username').value;
  const password = document.getElementById('password').value;

  const { data, error } = await supabaseClient.auth.signInWithPassword({ email, password });

  if (error) {
    alert("Hatalı giriş: " + error.message);
  } else {
    sessionStorage.setItem("user", "admin");
    alert("Başarıyla giriş yaptınız!");
    window.location.href = "home.html";
  }
});

// === KAYIT ===
document.getElementById('register-form')?.addEventListener('submit', async function(event) {
  event.preventDefault();
  const email = document.getElementById('new-username').value;
  const password = document.getElementById('new-password').value;

  const { data, error } = await supabaseClient.auth.signUp({ email, password });

  if (error) {
    alert("Kayıt hatası: " + error.message);
  } else {
    alert("Kayıt başarılı! Şimdi giriş yapabilirsiniz.");
    window.location.href = "index.html";
  }
});

// === MİSAFİR GİRİŞİ ===
function guestLogin() {
  sessionStorage.setItem("user", "guest");
  alert("Misafir olarak giriş yapıldı!");
  window.location.href = "home.html";
}

// === HOŞGELDİN MESAJI VE PROFİL YÜKLE ===
async function showWelcome() {
  const { data: { user } } = await supabaseClient.auth.getUser();
  if (!user) return;

  const { data, error } = await supabaseClient
    .from("profiles")
    .select("*")
    .eq("id", user.id)
    .single();

  if (data) {
    document.getElementById("welcome-message").textContent = `Hoş geldin, ${data.ad}!`;
    document.getElementById("mini-name").textContent = data.ad;
    if (data.photo) {
      document.getElementById("mini-profile").src = data.photo;
    }

    // input'lara da doldur
    document.getElementById("ad").value = data.ad || "";
    document.getElementById("soyad").value = data.soyad || "";
    document.getElementById("uni").value = data.uni || "";
    document.getElementById("hobiler").value = data.hobiler || "";
  }
}

// === PROFİL KAYDET (GELİŞMİŞ DEBUG'LU) ===
document.getElementById("profile-form")?.addEventListener("submit", async function (e) {
  e.preventDefault();

  const { data: { user }, error: userError } = await supabaseClient.auth.getUser();
  if (!user) {
    console.error("Kullanıcı oturumu alınamadı:", userError);
    alert("Giriş yapılmamış. Lütfen yeniden giriş yapın.");
    return;
  }

  console.log("Kullanıcı ID:", user.id);

  const ad = document.getElementById("ad").value;
  const soyad = document.getElementById("soyad").value;
  const uni = document.getElementById("uni").value;
  const hobiler = document.getElementById("hobiler").value;
  const photoInput = document.getElementById("photo-input");

  const userData = {
    id: user.id,
    ad,
    soyad,
    uni,
    hobiler,
  };

  const handleUpsert = async (dataToSave) => {
    const { error } = await supabaseClient.from("profiles").upsert(dataToSave);

    if (error) {
      console.error("Profil kaydetme hatası:", error.message);
      alert("Kaydetme başarısız: " + error.message);
    } else {
      console.log("Profil başarıyla kaydedildi.");
      showWelcome();
      toggleProfile();
    }
  };

  if (photoInput?.files?.[0]) {
    const reader = new FileReader();
    reader.onload = () => {
      userData.photo = reader.result;
      handleUpsert(userData);
    };
    reader.readAsDataURL(photoInput.files[0]);
  } else {
    handleUpsert(userData);
  }
});

// === ŞİFRE SIFIRLAMA (GÜNCELLENDİ) ===
document.getElementById("forgot-form")?.addEventListener("submit", async function (e) {
  e.preventDefault();
  const email = document.getElementById("forgot-email").value;
  const submitButton = e.target.querySelector('button[type="submit"]');
  
  // Button'u disable et ve loading göster
  submitButton.disabled = true;
  const originalText = submitButton.textContent;
  submitButton.textContent = 'Gönderiliyor...';

  try {
    const { data, error } = await supabaseClient.auth.resetPasswordForEmail(email, {
      redirectTo: 'https://sualpkula.com/reset.html'
    });

    if (error) {
      alert("Hata: " + error.message);
    } else {
      alert("Şifre sıfırlama e-postası gönderildi! E-posta kutunuzu kontrol edin.");
      document.getElementById("forgot-email").value = ''; // Formu temizle
    }
  } catch (err) {
    alert("Beklenmeyen hata: " + err.message);
  } finally {
    // Button'u tekrar aktif et
    submitButton.disabled = false;
    submitButton.textContent = originalText;
  }
});

// === LOGOUT FONKSİYONU ===
async function logout() {
  const confirmLogout = confirm("Çıkış yapmak istediğinize emin misiniz?");
  
  if (!confirmLogout) return;
  
  try {
    // Supabase'den çıkış yap
    const { error } = await supabaseClient.auth.signOut();
    
    if (error) {
      console.error("Logout hatası:", error.message);
      alert("Çıkış yapılırken hata oluştu: " + error.message);
      return;
    }
    
    // Session storage'ı temizle
    sessionStorage.removeItem("user");
    sessionStorage.clear(); // Tüm session'ı temizle
    
    // Local storage'ı da temizle (varsa)
    localStorage.clear();
    
    alert("Başarıyla çıkış yaptınız!");
    
    // Giriş sayfasına yönlendir
    window.location.href = "index.html";
    
  } catch (err) {
    console.error("Beklenmeyen logout hatası:", err);
    alert("Beklenmeyen bir hata oluştu. Sayfa yenilenecek.");
    window.location.reload();
  }
}

// === SAYFA YÜKLENDİĞİNDE SESSION KONTROLÜ ===
async function checkSession() {
  // Eğer home.html'deysek session kontrolü yap
  if (window.location.pathname.includes('home.html')) {
    const sessionUser = sessionStorage.getItem("user");
    const { data: { user } } = await supabaseClient.auth.getUser();
    
    // Ne session var ne de supabase user'ı varsa giriş sayfasına yönlendir
    if (!sessionUser && !user) {
      alert("Oturum süresi dolmuş. Lütfen tekrar giriş yapın.");
      window.location.href = "index.html";
      return false;
    }
  }
  return true;
}

// Sayfa yüklendiğinde session kontrolü yap
window.addEventListener('load', checkSession);
