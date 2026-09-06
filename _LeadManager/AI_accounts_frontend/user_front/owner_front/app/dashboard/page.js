'use client';
import { useState, useEffect, useCallback } from 'react';
import { useRouter } from 'next/navigation';

const API_URL = process.env.NEXT_PUBLIC_API_URL;

export default function Dashboard() {
  const router = useRouter();
  const [owner, setOwner] = useState(null);
  const [bookings, setBookings] = useState([]);
  const [selectedBooking, setSelectedBooking] = useState(null);
  const [messages, setMessages] = useState([]);
  const [chatInput, setChatInput] = useState('');
  const [sending, setSending] = useState(false);
  const [showChat, setShowChat] = useState(false);

  useEffect(() => {
    const stored = localStorage.getItem('owner');
    if (!stored) { router.push('/login'); return; }
    setOwner(JSON.parse(stored));
  }, []);

  const fetchBookings = useCallback(async () => {
    if (!owner) return;
    try {
      const res = await fetch(`${API_URL}/api_sp_leads.php?sp_id=${owner.id}`, {
        method: 'GET',
        headers: { 'Content-Type': 'application/json' },
      });
      const json = await res.json();
      if (json.leads) setBookings(json.leads);
    } catch (e) {}
  }, [owner]);

  useEffect(() => {
    fetchBookings();
    const interval = setInterval(fetchBookings, 5000);
    return () => clearInterval(interval);
  }, [fetchBookings]);

  const handleAccept = async (booking) => {
    try {
      await fetch(`${API_URL}/api_sp_chat.php?sp_id=${owner.id}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ order_id: booking.id, message: 'accept' }),
      });
      setSelectedBooking(booking);
      setShowChat(true);
      setMessages([]);
      fetchBookings();
    } catch (e) {}
  };

  const handleReject = async (booking) => {
    try {
      await fetch(`${API_URL}/api_sp_chat.php?sp_id=${owner.id}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ order_id: booking.id, message: 'reject' }),
      });
      fetchBookings();
    } catch (e) {}
  };

  /* ============================================================
     FIX: real polling instead of a one-time "__auto_brief__" call.
     Previously this only ran ONCE when the chat opened and never
     again — so the provider could send messages (they saved fine)
     but never saw the customer's replies. Now it re-fetches the
     full conversation from the server every few seconds, exactly
     like the customer's booking-chat page already does.
     ============================================================ */
  const fetchMessages = useCallback(async () => {
    if (!selectedBooking || !owner) return;
    try {
      const res = await fetch(
        `${API_URL}/api_sp_chat.php?sp_id=${owner.id}&order_id=${selectedBooking.id}`,
        { method: 'GET' }
      );
      const json = await res.json();
      if (json.messages) {
        setMessages(
          json.messages.map((m) => ({
            sender_type: m.sender_role,
            message: m.message,
            time: m.created_at,
          }))
        );
      }
    } catch (e) {}
  }, [selectedBooking, owner]);

  useEffect(() => {
    if (!showChat) return;
    fetchMessages();
    const interval = setInterval(fetchMessages, 3000);
    return () => clearInterval(interval);
  }, [showChat, fetchMessages]);

  const sendMessage = async () => {
    if (!chatInput.trim() || sending) return;
    const text = chatInput.trim();
    setSending(true);
    setChatInput('');
    try {
      await fetch(`${API_URL}/api_sp_chat.php?sp_id=${owner.id}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ order_id: selectedBooking.id, message: text }),
      });
      // Re-fetch immediately so the provider's own message (and any
      // reply that may already be waiting) shows up right away,
      // instead of only appearing locally without ever syncing.
      fetchMessages();
    } catch (e) {}
    setSending(false);
  };

  const handleLogout = () => {
    localStorage.removeItem('owner');
    router.push('/login');
  };

  if (!owner) return null;

  return (
    <div className="min-h-screen bg-gray-950 text-white">
      <div className="bg-gray-900 px-6 py-4 flex items-center justify-between border-b border-gray-800">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 bg-green-600 rounded-full flex items-center justify-center text-xl">🏪</div>
          <div>
            <h1 className="font-bold text-lg">Provider Dashboard</h1>
            <p className="text-xs text-green-400">● {owner.name}</p>
          </div>
        </div>
        <button onClick={handleLogout} className="text-xs bg-gray-800 hover:bg-gray-700 text-gray-300 px-3 py-1.5 rounded-lg transition">Logout</button>
      </div>

      <div className="flex h-[calc(100vh-72px)]">
        <div className="w-full md:w-1/2 border-r border-gray-800 overflow-y-auto p-4">
          <h2 className="text-lg font-semibold mb-4 text-gray-200">
            📋 New Requests{' '}
            {bookings.length > 0 && <span className="ml-2 bg-red-500 text-white text-xs px-2 py-0.5 rounded-full">{bookings.length}</span>}
          </h2>

          {bookings.length === 0 ? (
            <div className="text-center text-gray-500 mt-20">
              <p className="text-4xl mb-3">📭</p>
              <p>No new requests yet</p>
              <p className="text-xs mt-1">Checking every 5 seconds...</p>
            </div>
          ) : (
            bookings.map((b) => (
              <div key={b.id} className={`bg-gray-900 border rounded-xl p-4 mb-3 transition ${selectedBooking?.id === b.id ? 'border-green-500' : 'border-gray-800'}`}>
                <div className="flex justify-between items-start mb-2">
                  <p className="font-semibold text-white">#{b.booking_code}</p>
                  <span className="text-xs px-2 py-1 rounded-full font-medium bg-yellow-900 text-yellow-300">{b.status}</span>
                </div>
                <p className="text-sm text-gray-300 mb-1">📍 {b.user_address || 'No address'}</p>
                <p className="text-sm text-gray-400 mb-3 line-clamp-2">📝 {b.description}</p>
                {b.amount > 0 && <p className="text-sm text-green-400 mb-3">💰 ₹{b.amount}</p>}
                <div className="flex gap-2">
                  <button onClick={() => handleAccept(b)} className="flex-1 bg-green-600 hover:bg-green-700 text-white rounded-lg py-2 text-sm font-medium transition">✅ Accept</button>
                  <button onClick={() => handleReject(b)} className="flex-1 bg-red-700 hover:bg-red-800 text-white rounded-lg py-2 text-sm font-medium transition">❌ Reject</button>
                </div>
              </div>
            ))
          )}
        </div>

        <div className="hidden md:flex flex-col w-1/2">
          {!showChat ? (
            <div className="flex-1 flex items-center justify-center text-gray-600">
              <div className="text-center">
                <p className="text-5xl mb-3">💬</p>
                <p>Accept a request to start chatting</p>
              </div>
            </div>
          ) : (
            <>
              <div className="bg-gray-900 px-4 py-3 border-b border-gray-800">
                <p className="font-semibold">Chat — #{selectedBooking?.booking_code}</p>
              </div>
              <div className="flex-1 overflow-y-auto px-4 py-4 space-y-3">
                {messages.map((msg, i) => {
                  const isProvider = String(msg.sender_type || '').trim().toLowerCase() === 'sp';
                  return (
                    <div key={i} className={`flex ${isProvider ? 'justify-end' : 'justify-start'}`}>
                      <div className={`max-w-xs px-4 py-2 rounded-2xl text-sm ${isProvider ? 'bg-green-600 text-white rounded-br-none' : 'bg-gray-800 text-gray-100 rounded-bl-none'}`}>
                        {!isProvider && <p className="text-xs text-gray-400 mb-1">👤 Customer</p>}
                        {msg.message}
                      </div>
                    </div>
                  );
                })}
                {messages.length === 0 && <p className="text-center text-gray-600 text-sm mt-10">No messages yet. Say hello! 👋</p>}
              </div>
              <div className="bg-gray-900 px-4 py-3 border-t border-gray-800 flex gap-3">
                <input
                  className="flex-1 bg-gray-800 text-white rounded-full px-4 py-2 text-sm outline-none focus:ring-2 focus:ring-green-500"
                  placeholder="Type a message..."
                  value={chatInput}
                  onChange={(e) => setChatInput(e.target.value)}
                  onKeyDown={(e) => e.key === 'Enter' && sendMessage()}
                />
                <button onClick={sendMessage} disabled={sending} className="bg-green-600 hover:bg-green-700 text-white rounded-full w-10 h-10 flex items-center justify-center transition">➤</button>
              </div>
            </>
          )}
        </div>
      </div>
    </div>
  );
}