const SUPABASE_URL = "https://bvlyzxljieftbkzkdwzv.supabase.co";
const SUPABASE_KEY = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImJ2bHl6eGxqaWVmdGJremtkd3p2Iiwicm9sZSI6ImFub24iLCJpYXQiOjE3NTA4ODQxNTgsImV4cCI6MjA2NjQ2MDE1OH0.mJEavNb2WC_0pBpg8KJq0ABc2hquYTewoge38U5P7dw";

const supabaseClient = supabase.createClient(SUPABASE_URL, SUPABASE_KEY);

// Kullanıcı bilgilerini localStorage’a yükle
async function loadUserProfile(userId) {
  const { data, error } = await supabaseClient
    .from("profiles")
    .select("*")
    .eq("id", userId)
    .single();

  if (!error && data) {
    localStorage.setItem("userProfile", JSON.stringify(data));
  }
}

// GİRİŞ
document.getElementById("login-form")?.addEventListener("submit", async function (event) {
  event.preventDefault();
  const email = document.getElementById("username").value;
  const password = document.getElementById("password").value;

  const { data, error } = await supabaseClient.auth.signInWithPassword({ email, password });

  if (error) {
    alert("Hatalı giriş: " + error.message);
  } else {
    sessionStorage.setItem("user", "admin");
    await loadUserProfile(data.user.id);
    alert("Başarıyla giriş yaptınız!");
    window.location.href = "home.html";
  }
});

// KAYIT
document.getElementById("register-form")?.addEventListener("submit", async function (event) {
  event.preventDefault();
  const email = document.getElementById("new-username").value;
  const password = document.getElementById("new-password").value;

  const { data, error } = await supabaseClient.auth.signUp({ email, password });

  if (error) {
    alert("Kayıt hatası: " + error.message);
  } else {
    alert("Kayıt başarılı! Şimdi giriş yapabilirsiniz.");
    window.location.href = "index.html";
  }
});

// MİSAFİR GİRİŞİ
function guestLogin() {
  sessionStorage.setItem("user", "guest");
  alert("Misafir olarak giriş yapıldı!");
  window.location.href = "home.html";
}
