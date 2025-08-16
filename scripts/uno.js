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
        // Her renk için 0'dan 9'a kadar kartları oluştur (0 tek, diğerleri ikişer tane)
        colors.forEach(color => {
            this.cards.push(new Card(color, "0"));
            for (let i = 0; i < 2; i++) {
                values.slice(1).forEach(value => {
                    this.cards.push(new Card(color, value));
                });
            }
        });
        
        // Wild ve Wild Draw 4 kartlarını ekle (dörder tane)
        for (let i = 0; i < 4; i++) {
            this.cards.push(new Card("black", "wild"));
            this.cards.push(new Card("black", "wildDraw4"));
        }
    }

    shuffle() {
        // Fisher-Yates shuffle algoritması
        for (let i = this.cards.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [this.cards[i], this.cards[j]] = [this.cards[j], this.cards[i]];
        }
    }

    deal(numCards) {
        const dealtCards = this.cards.splice(0, numCards);
        return dealtCards;
    }

    draw() {
        return this.cards.pop();
    }
}

// Oyunu başlatmak için örnek kullanım
const myDeck = new Deck();
console.log("Deste oluşturuldu ve karıştırıldı. Toplam kart sayısı:", myDeck.cards.length);

const playerHand = myDeck.deal(7);
console.log("Oyuncuya 7 kart dağıtıldı:", playerHand);

const topCard = myDeck.draw();
console.log("Desteden çekilen ilk kart:", topCard);
