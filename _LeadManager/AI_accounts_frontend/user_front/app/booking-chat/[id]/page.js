'use client';
import { useState, useEffect, useRef, useCallback } from 'react';
import { useRouter, useParams } from 'next/navigation';

const API_URL = process.env.NEXT_PUBLIC_API_URL;

export default function BookingChat() {
  const router = useRouter();
  const { id } = useParams();
  const [user, setUser] = useState(null);
  const [messages, setMessages] = useState([]);
  const [input, setInput] = useState('');
  const [sending, setSending] = useState(false);
  const [status, setStatus] = useState('waiting');
  const bottomRef = useRef(null);

  useEffect(() => {
    const stored = localStorage.getItem('user');
    if (!stored) { router.push('/login'); return; }
    setUser(JSON.parse(stored));
  }, []);

  useEffect(() => {
    bottomRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages]);

 const fetchMessages = useCallback(async () => {
    if (!user || !id) return;
    try {
      const res = await fetch(
        `${API_URL}/api_sp_to_user_messages.php?booking_id=${id}&user_id=${user.id}`,
        { method: 'GET' }
      );
      const json = await res.json();
      if (json.booking) setStatus(json.booking.status);
      if (json.messages) {
        setMessages(json.messages.map(m => ({
          role: m.sender_role,
          text: m.message,
          time: m.created_at,
        })));
      }
    } catch (e) {}
  }, [user, id]);

  useEffect(() => {
    if (!user) return;
    fetchMessages();
    const interval = setInterval(fetchMessages, 3000);
    return () => clearInterval(interval);
  }, [user, fetchMessages]);

  const sendMessage = async () => {
    if (!input.trim() || sending) return;
    const text = input.trim();
    setInput('');
    setSending(true);
    try {
      await fetch(`${API_URL}/api_sp_to_user_messages.php`, {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    booking_id: parseInt(id),
    user_id: user.id,
    message: text,
    sender_role: 'user',
  }),
});
      fetchMessages();
    } catch (e) {}
    setSending(false);
  };

  if (!user) return null;

  return (
    <div className="flex flex-col h-screen bg-gray-950 text-white">
      {/* Header */}
      <div className="bg-gray-900 px-6 py-4 flex items-center justify-between border-b border-gray-800">
        <div className="flex items-center gap-3">
          <button onClick={() => router.back()} className="text-gray-400 hover:text-white mr-2">←</button>
          <div className="w-10 h-10 rounded-full bg-green-600 flex items-center justify-center text-xl">🏪</div>
          <div>
            <h1 className="font-bold text-lg">Service Provider</h1>
            <p className={`text-xs ${status === 'searching' || status === 'accepted' ? 'text-green-400' : 'text-yellow-400'}`}>
              {status === 'waiting' || status === 'pending' ? '⏳ Waiting for provider...' : '● Connected'}
            </p>
          </div>
        </div>
        <span className="text-xs text-gray-500">Booking #{id}</span>
      </div>

      {/* Messages */}
      <div className="flex-1 overflow-y-auto px-4 py-6 space-y-4">
        {status === 'pending' && messages.length === 0 && (
          <div className="text-center text-gray-500 mt-20">
            <p className="text-4xl mb-3">⏳</p>
            <p className="font-medium">Waiting for provider to accept...</p>
            <p className="text-xs mt-1">This page will update automatically</p>
          </div>
        )}

        {messages.map((msg, i) => (
          <div key={i} className={`flex ${msg.role === 'user' ? 'justify-end' : 'justify-start'}`}>
            <div className={`max-w-xs md:max-w-md px-4 py-3 rounded-2xl text-sm shadow ${
              msg.role === 'user'
                ? 'bg-blue-600 text-white rounded-br-none'
                : 'bg-gray-800 text-gray-100 rounded-bl-none'
            }`}>
              {msg.role === 'provider' && <p className="text-xs text-gray-400 mb-1">🏪 Provider</p>}
              {msg.text}
            </div>
          </div>
        ))}
        <div ref={bottomRef} />
      </div>

      {/* Input */}
      <div className="bg-gray-900 px-4 py-4 border-t border-gray-800 flex gap-3 items-center">
        <input
          className="flex-1 bg-gray-800 text-white rounded-full px-5 py-3 text-sm outline-none placeholder-gray-500 focus:ring-2 focus:ring-blue-500 disabled:opacity-50"
          placeholder={status === 'pending' ? 'Waiting for provider...' : 'Type a message...'}
          value={input}
          onChange={(e) => setInput(e.target.value)}
          onKeyDown={(e) => e.key === 'Enter' && sendMessage()}
          disabled={sending || status === 'pending'}
        />
        <button
          onClick={sendMessage}
          disabled={sending || status === 'pending'}
          className="bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white rounded-full w-12 h-12 flex items-center justify-center text-xl transition"
        >
          ➤
        </button>
      </div>
    </div>
  );
}