// Kart renkleri ve değerleri
const colors = ["red", "green", "blue", "yellow"];
const values = [
    "0", "1", "2", "3", "4", "5", "6", "7", "8", "9", "skip", "reverse", "draw2"
];

// Özel Wild kartlar
const wildCards = ["wild", "wildDraw4"];

// Kart sınıfı
class Card {
    constructor(color, value) {
        this.color = color;
        this.value = value;
    }
}

// Deste sınıfı
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

// === Yeni Eklenecek Kodlar ===

// HTML'e kart eklemek için fonksiyon
function createCardElement(card, isBack = false) {
    const cardElement = document.createElement("div");
    cardElement.className = `card ${isBack ? 'card-back' : card.color}`;
    cardElement.dataset.color = card.color;
    cardElement.dataset.value = card.value;

    if (!isBack) {
        const cardValue = document.createElement("span");
        cardValue.textContent = card.value;
        cardElement.appendChild(cardValue);
    }

    return cardElement;
}

// Oyun başlatan ana fonksiyon
function startGame() {
    const myDeck = new Deck();
    const yourHand = myDeck.deal(7);
    const opponentHand = myDeck.deal(7);
    const discardPile = [myDeck.draw()];

    // HTML elementlerini al
    const yourCardsContainer = document.getElementById("your-cards");
    const opponentCardsContainer = document.getElementById("opponent-cards");
    const discardPileContainer = document.getElementById("discard-pile");

    // Senin elindeki kartları ekrana bas
    yourHand.forEach(card => {
        yourCardsContainer.appendChild(createCardElement(card));
    });

    // Rakibin elindeki kartları ekrana bas (sadece arka yüzleri)
    opponentHand.forEach(card => {
        opponentCardsContainer.appendChild(createCardElement(card, true));
    });

    // Atılan ilk kartı ekrana bas
    if (discardPile.length > 0) {
        discardPileContainer.appendChild(createCardElement(discardPile[0]));
    }
}

// Sayfa yüklendiğinde oyunu başlat
window.addEventListener('DOMContentLoaded', startGame);
