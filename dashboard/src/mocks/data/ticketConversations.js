import { cloneMockData } from "../envelope.js";

// baseTime = "2026-06-28T08:00:00.000Z"
// q_2003 created_at: at(-18) → 2026-06-28T07:42:00.000Z
// q_2004 created_at: at(-9)  → 2026-06-28T07:51:00.000Z

const conversationSeed = {
  q_2003: [
    {
      id: "msg_3001",
      author_type: "guest",
      author_name: "Amal Nassar",
      body: "Hi, we land at 11:45 PM. Is there a transfer available?",
      sent_at: "2026-06-28T07:42:00.000Z",
    },
    {
      id: "msg_3002",
      author_type: "staff",
      author_name: "Concierge",
      body: "Hello Ms. Nassar, yes we offer late-night transfers. Rate is $35 one-way.",
      sent_at: "2026-06-28T07:49:00.000Z",
    },
    {
      id: "msg_3003",
      author_type: "guest",
      author_name: "Amal Nassar",
      body: "That works perfectly, please arrange it.",
      sent_at: "2026-06-28T07:55:00.000Z",
    },
    {
      id: "msg_3004",
      author_type: "staff",
      author_name: "Concierge",
      body: "Booked. Driver details will be sent to you this evening, and the car will wait at the main entrance.",
      sent_at: "2026-06-28T08:03:00.000Z",
    },
  ],
  q_2004: [
    {
      id: "msg_4001",
      author_type: "guest",
      author_name: "Dana Abboud",
      body: "I see a minibar charge for 45 SAR but I never touched it.",
      sent_at: "2026-06-28T07:51:00.000Z",
    },
    {
      id: "msg_4002",
      author_type: "staff",
      author_name: "Reception",
      body: "Ms. Abboud, I'm pulling up your folio now to verify.",
      sent_at: "2026-06-28T07:57:00.000Z",
    },
    {
      id: "msg_4003",
      author_type: "staff",
      author_name: "Reception",
      body: "I've reviewed the charge — it appears to be an error. I'm removing it now.",
      sent_at: "2026-06-28T08:01:00.000Z",
    },
    {
      id: "msg_4004",
      author_type: "guest",
      author_name: "Dana Abboud",
      body: "Thank you, much appreciated.",
      sent_at: "2026-06-28T08:06:00.000Z",
    },
    {
      id: "msg_4005",
      author_type: "staff",
      author_name: "Duty Manager",
      body: "The credit is posted, a fruit amenity is on the way, and we will call once the folio is refreshed.",
      sent_at: "2026-06-28T08:10:00.000Z",
    },
  ],
};

let conversations = cloneMockData(conversationSeed);

export function getTicketConversation(ticketId) {
  return cloneMockData(conversations[ticketId] || []);
}

export function addMessageToTicket(ticketId, message) {
  if (!conversations[ticketId]) {
    conversations[ticketId] = [];
  }
  conversations[ticketId].push(message);
  return cloneMockData(message);
}

export function resetTicketConversations() {
  conversations = cloneMockData(conversationSeed);
}
