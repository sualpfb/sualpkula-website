// UNO.js
const SUPABASE_URL = "https://bvlyzxljieftbkzkdwzv.supabase.co";
const SUPABASE_KEY = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImJ2bHl6eGxqaWVmdGJremtkd3p2Iiwicm9sZSI6ImFub24iLCJpYXQiOjE3NTA4ODQxNTgsImV4cCI6MjA2NjQ2MDE1OH0.mJEavNb2WC_0pBpg8KJq0ABc2hquYTewoge38U5P7dw";
const supabase = window.supabase.createClient(SUPABASE_URL, SUPABASE_KEY);

let currentUserEmail = null;
let currentUserId = null;
let currentGameId = null;

const colors = ["red", "green", "blue", "yellow"];
const values = ["0", "1", "2", "3", "4", "5", "6", "7", "8", "9", "skip", "reverse", "draw2"];
const wildCards = ["wild", "wildDraw4"];

class Card {
    constructor(color, value) {
        this.color = color;
        this.value = value;
    }
}

class Deck {
    constructor() {
        this.cards = [];
        this.createDeck();
        this.shuffle();
    }
    
    createDeck() {
        colors.forEach(color => {
            this.cards.push(new Card(color, "0"));
            for (let i = 0; i < 2; i++) {
                values.slice(1).forEach(value => {
                    this.cards.push(new Card(color, value));
                });
            }
        });
        
        for (let i = 0; i < 4; i++) {
            this.cards.push(new Card("black", "wild"));
            this.cards.push(new Card("black", "wildDraw4"));
        }
    }
    
    shuffle() {
        for (let i = this.cards.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [this.cards[i], this.cards[j]] = [this.cards[j], this.cards[i]];
        }
    }
    
    deal(numCards) {
        return this.cards.splice(0, numCards);
    }
    
    draw() {
        return this.cards.pop();
    }
}

const gameState = {
    deck: [],
    players: [],
    hands: {},
    discardPile: [],
    currentPlayerIndex: 0
};

const lobbySection = document.getElementById('lobby-section');
const gameArea = document.querySelector('.game-area');
const createGameBtn = document.getElementById('create-game-btn');
const joinGameBtn = document.getElementById('join-game-btn');
const gameIdInput = document.getElementById('game-id-input');
const statusMessage = document.getElementById('status-message');

function renderCards() {
    const yourCardsContainer = document.getElementById("your-cards");
    const opponentCardsContainer = document.getElementById("opponent-cards");
    const discardPileContainer = document.getElementById("discard-pile");

    yourCardsContainer.innerHTML = '';
    opponentCardsContainer.innerHTML = '';
    discardPileContainer.innerHTML = '';
    
    if (gameState.hands[currentUserId]) {
        gameState.hands[currentUserId].forEach(card => {
            const cardEl = document.createElement("div");
            cardEl.className = `card ${card.color}`;
            cardEl.dataset.color = card.color;
            cardEl.dataset.value = card.value;
            cardEl.textContent = card.value;
            yourCardsContainer.appendChild(cardEl);
        });
    }

    gameState.players.filter(p => p !== currentUserId).forEach(opponentId => {
        const numCards = gameState.hands[opponentId] ? gameState.hands[opponentId].length : 0;
        opponentCardsContainer.innerHTML = '';
        for (let i = 0; i < numCards; i++) {
            const cardEl = document.createElement("div");
            cardEl.className = 'card card-back';
            opponentCardsContainer.appendChild(cardEl);
        }
    });

    if (gameState.discardPile.length > 0) {
        const topCard = gameState.discardPile[gameState.discardPile.length - 1];
        const cardEl = document.createElement("div");
        cardEl.className = `card ${topCard.color}`;
        cardEl.dataset.color = topCard.color;
        cardEl.dataset.value = topCard.value;
        cardEl.textContent = topCard.value;
        discardPileContainer.appendChild(cardEl);
    }
}

function renderGame(game) {
    Object.assign(gameState, game);
    lobbySection.style.display = 'none';
    gameArea.style.display = 'flex';
    renderCards();
    statusMessage.textContent = `Oyun ID: ${game.id}`;
}

function listenForGameUpdates(gameId) {
    supabase.channel(`game:${gameId}`)
        .on('postgres_changes', { event: 'UPDATE', schema: 'public', table: 'games', filter: `id=eq.${gameId}` }, payload => {
            console.log('Veritabanından güncelleme geldi:', payload.new);
            renderGame(payload.new);
        })
        .subscribe();
}

async function createGame() {
    if (!currentUserId) {
        statusMessage.textContent = "Kullanıcı bilgileri yükleniyor, lütfen bekleyin.";
        return;
    }
    const deck = new Deck();
    const yourHand = deck.deal(7);
    const firstCard = deck.draw();
    
    const newGame = {
        players: [currentUserId], // Hata buradan kaynaklanıyordu, currentUserEmail yerine currentUserId kullanıldı
        player_count: 1,
        deck: deck.cards,
        discard_pile: [firstCard],
        hands: { [currentUserId]: yourHand }, // Burası da güncellendi
        current_player_index: 0
    };

    const { data, error } = await supabase.from('games').insert(newGame).select();

    if (error) {
        statusMessage.textContent = "Oyun oluşturma hatası: " + error.message;
        console.error(error);
        return;
    }

    const gameId = data[0].id;
    currentGameId = gameId;
    statusMessage.textContent = `Oyununuz oluşturuldu! ID: ${gameId}. Arkadaşını davet et.`;
    renderGame(data[0]);
    listenForGameUpdates(gameId);
}

async function joinGame() {
    if (!currentUserId) {
        statusMessage.textContent = "Kullanıcı bilgileri yükleniyor, lütfen bekleyin.";
        return;
    }
    const gameId = gameIdInput.value;
    if (!gameId) {
        statusMessage.textContent = "Lütfen bir oyun ID'si girin.";
        return;
    }

    const { data, error } = await supabase.from('games').select('players, hands, deck, player_count').eq('id', gameId).single();

    if (error) {
        statusMessage.textContent = "Oyun bulunamadı veya katılım mümkün değil.";
        console.error(error);
        return;
    }

    if (data.player_count >= 4) {
        statusMessage.textContent = "Oyun zaten dolu.";
        return;
    }
    
    if (data.players.includes(currentUserId)) {
        statusMessage.textContent = "Bu oyuna zaten katıldınız.";
        renderGame(data);
        listenForGameUpdates(gameId);
        return;
    }
    
    const newPlayers = [...data.players, currentUserId];
    const newHands = { ...data.hands, [currentUserId]: data.deck.splice(0, 7) };
    
    const { error: updateError, data: updatedData } = await supabase.from('games').update({
        players: newPlayers,
        hands: newHands,
        deck: data.deck,
        player_count: newPlayers.length
    }).eq('id', gameId).select();
    
    if (updateError) {
        statusMessage.textContent = "Oyuna katılırken hata oluştu: " + updateError.message;
        console.error(updateError);
        return;
    }

    currentGameId = gameId;
    statusMessage.textContent = `Oyuna katıldın! ID: ${gameId}`;
    renderGame(updatedData[0]);
    listenForGameUpdates(gameId);
}

window.addEventListener('DOMContentLoaded', async () => {
    statusMessage.textContent = "Oturum kontrol ediliyor...";
    createGameBtn.disabled = true;
    joinGameBtn.disabled = true;

    const { data: { user }, error } = await supabase.auth.getUser();
    if (error || !user) {
        alert("Oturum süresi dolmuş veya giriş yapılmamış. Lütfen tekrar giriş yapın.");
        window.location.href = "index.html";
        return;
    }

    currentUserEmail = user.email;
    currentUserId = user.id;
    statusMessage.textContent = "Hazır.";
    createGameBtn.disabled = false;
    joinGameBtn.disabled = false;
    console.log("Kullanıcı doğrulandı:", currentUserEmail, currentUserId);
});

createGameBtn.addEventListener('click', createGame);
joinGameBtn.addEventListener('click', joinGame);
