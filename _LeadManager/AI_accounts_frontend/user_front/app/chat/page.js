"use client";
import { useState, useRef, useEffect, useCallback } from "react";
import { useRouter } from "next/navigation";

const API_URL = process.env.NEXT_PUBLIC_API_URL;

const INITIAL_MESSAGE = {
  role: "bot",
  text: "Hello! 👋 I am your AI Assistant. What service do you need? (e.g. Plumber, Electrician, Coffee Order...)",
};

function statusColor(status) {
  switch (status) {
    case "completed":
      return "bg-green-900 text-green-300";
    case "accepted":
      return "bg-blue-900 text-blue-300";
    case "rejected":
    case "cancelled":
      return "bg-red-900 text-red-300";
    default:
      return "bg-yellow-900 text-yellow-300";
  }
}

export default function ChatPage() {
  const router = useRouter();
  const [user, setUser] = useState(null);
  const [messages, setMessages] = useState([INITIAL_MESSAGE]);
  const [input, setInput] = useState("");
  const [loading, setLoading] = useState(false);
  const [bookingDone, setBookingDone] = useState(false);
  const bottomRef = useRef(null);

  // ---- sidebar state (NEW) ----
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const [bookings, setBookings] = useState([]);
  const [bookingsLoading, setBookingsLoading] = useState(true);

  useEffect(() => {
    const stored = localStorage.getItem("user");
    if (!stored) {
      router.push("/login");
      return;
    }
    setUser(JSON.parse(stored));
  }, []);

  useEffect(() => {
    bottomRef.current?.scrollIntoView({ behavior: "smooth" });
  }, [messages]);

  // ---- sidebar: fetch booking history (NEW) ----
  const fetchBookings = useCallback(async () => {
    if (!user) return;
    try {
      const res = await fetch(`${API_URL}/api_user_bookings.php?user_id=${user.id}`);
      const json = await res.json();
      if (json.status === "ok") {
        setBookings(json.bookings);
      }
    } catch (err) {
      // silent — history is non-critical
    } finally {
      setBookingsLoading(false);
    }
  }, [user]);

  useEffect(() => {
    fetchBookings();
  }, [fetchBookings]);

  // ---- sidebar: New Chat button (NEW) ----
  const startNewChat = () => {
    setMessages([INITIAL_MESSAGE]);
    setBookingDone(false);
    setInput("");
    setSidebarOpen(false);
    fetchBookings();
  };

  // ---- sidebar: open a past booking's chat (NEW) ----
  const openBooking = (bookingId) => {
    router.push(`/booking-chat/${bookingId}`);
  };

  const sendMessage = async () => {
    if (!input.trim() || loading || !user) return;

    const userText = input.trim();
    setInput("");
    setMessages((prev) => [...prev, { role: "user", text: userText }]);
    setLoading(true);

    try {
      const res = await fetch(`${API_URL}/api_chat.php`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          user_id: user.id,
          user_name: user.name,
          message: userText,
        }),
      });

      const json = await res.json();

      if (json.reply) {
        setMessages((prev) => [...prev, { role: "bot", text: json.reply }]);
      }

    if (json.booking) {
  const bookingId = json.booking.id;
  const bookingCode = json.booking.booking_code;
  setMessages((prev) => [
    ...prev,
    {
      role: "system",
      text: `✅ Booking Confirmed! Code: ${bookingCode} — Connecting to provider...`,
    },
  ]);
  console.log('Booking ID:', bookingId);
  setBookingDone(true);
  fetchBookings();
  setTimeout(() => {
    if (bookingId) {
      router.push(`/booking-chat/${bookingId}`);
    }
  }, 2000);
}
    } catch (err) {
      setMessages((prev) => [
        ...prev,
        { role: "bot", text: "❌ Server error. Please try again." },
      ]);
    } finally {
      setLoading(false);
    }
  };

  const handleKey = (e) => {
    if (e.key === "Enter") sendMessage();
  };

  const handleLogout = () => {
    localStorage.removeItem("user");
    router.push("/login");
  };

  if (!user) return null;

  return (
    <div className="flex h-screen bg-gray-950 text-white">
      {/* ---- Sidebar (NEW) ---- */}
      <div
        className={`fixed md:static inset-y-0 left-0 z-30 w-72 bg-gray-900 border-r border-gray-800 flex flex-col transform transition-transform duration-200 ${
          sidebarOpen ? "translate-x-0" : "-translate-x-full"
        } md:translate-x-0`}
      >
        <div className="p-4 border-b border-gray-800">
          <button
            onClick={startNewChat}
            className="w-full flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl py-2.5 px-4 text-sm font-medium transition"
          >
            <span className="text-lg">+</span> New Chat
          </button>
        </div>

        <div className="flex-1 overflow-y-auto px-3 py-3">
          <p className="text-xs uppercase tracking-wide text-gray-500 px-2 mb-2">History</p>

          {bookingsLoading ? (
            <p className="text-sm text-gray-500 px-2">Loading...</p>
          ) : bookings.length === 0 ? (
            <p className="text-sm text-gray-600 px-2">No past orders yet.</p>
          ) : (
            <div className="space-y-1.5">
              {bookings.map((b) => (
                <button
                  key={b.id}
                  onClick={() => openBooking(b.id)}
                  className="w-full text-left bg-gray-800/60 hover:bg-gray-800 rounded-lg px-3 py-2.5 transition"
                >
                  <div className="flex items-center justify-between mb-1">
                    <span className="text-sm font-medium text-gray-100 truncate">
                      {b.category_name || "Service"}
                    </span>
                    <span className={`text-[10px] px-2 py-0.5 rounded-full font-medium shrink-0 ml-2 ${statusColor(b.status)}`}>
                      {b.status}
                    </span>
                  </div>
                  <p className="text-xs text-gray-500 truncate">#{b.booking_code}</p>
                  {b.provider_name && (
                    <p className="text-xs text-gray-500 truncate">👤 {b.provider_name}</p>
                  )}
                </button>
              ))}
            </div>
          )}
        </div>
      </div>

      {/* Overlay for mobile when sidebar open (NEW) */}
      {sidebarOpen && (
        <div
          className="fixed inset-0 bg-black/50 z-20 md:hidden"
          onClick={() => setSidebarOpen(false)}
        />
      )}

      {/* ---- Main chat (unchanged, only wrapped) ---- */}
      <div className="flex flex-col flex-1 min-w-0">
        {/* Header */}
        <div className="bg-gray-900 px-6 py-4 flex items-center justify-between shadow-lg border-b border-gray-800">
          <div className="flex items-center gap-3">
            <button
              onClick={() => setSidebarOpen(true)}
              className="md:hidden text-gray-400 hover:text-white text-xl px-1"
            >
              ☰
            </button>
            <div className="w-10 h-10 rounded-full bg-blue-600 flex items-center justify-center text-xl">
              🤖
            </div>
            <div>
              <h1 className="font-bold text-lg">AI Service Assistant</h1>
              <p className="text-xs text-green-400">● Online</p>
            </div>
          </div>
          <div className="flex items-center gap-3">
            <span className="text-sm text-gray-400">👤 {user.name}</span>
            <button
              onClick={handleLogout}
              className="text-xs bg-gray-800 hover:bg-gray-700 text-gray-300 px-3 py-1.5 rounded-lg transition"
            >
              Logout
            </button>
          </div>
        </div>

        {/* Messages */}
        <div className="flex-1 overflow-y-auto px-4 py-6 space-y-4">
          {messages.map((msg, i) => (
            <div
              key={i}
              className={`flex ${msg.role === "user" ? "justify-end" : "justify-start"}`}
            >
              {msg.role === "system" ? (
                <div className="mx-auto bg-green-800 text-green-100 px-4 py-2 rounded-xl text-sm font-semibold">
                  {msg.text}
                </div>
              ) : (
                <div
                  className={`max-w-xs md:max-w-md px-4 py-3 rounded-2xl text-sm shadow ${
                    msg.role === "user"
                      ? "bg-blue-600 text-white rounded-br-none"
                      : "bg-gray-800 text-gray-100 rounded-bl-none"
                  }`}
                >
                  {msg.text}
                </div>
              )}
            </div>
          ))}

          {loading && (
            <div className="flex justify-start">
              <div className="bg-gray-800 px-4 py-3 rounded-2xl rounded-bl-none text-sm text-gray-400 animate-pulse">
                AI is typing...
              </div>
            </div>
          )}
          <div ref={bottomRef} />
        </div>

        {/* Input */}
        <div className="bg-gray-900 px-4 py-4 border-t border-gray-800 flex gap-3 items-center">
          <input
            className="flex-1 bg-gray-800 text-white rounded-full pl-5 pr-4 py-3 text-sm outline-none placeholder-gray-500 focus:ring-2 focus:ring-blue-500 disabled:opacity-50"
            placeholder={
              bookingDone
                ? "Booking done! Start new chat..."
                : "Type your service... (e.g. I need a plumber)"
            }
            value={input}
            onChange={(e) => setInput(e.target.value)}
            onKeyDown={handleKey}
            disabled={loading}
          />
          <button
            onClick={sendMessage}
            disabled={loading}
            className="bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white rounded-full w-12 h-12 flex items-center justify-center text-xl transition"
          >
            ➤
          </button>
        </div>
      </div>
    </div>
  );
}