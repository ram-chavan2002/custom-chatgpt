'use client';
import { useState, useRef, useEffect } from 'react';

const API_URL = process.env.NEXT_PUBLIC_API_URL;

export default function Home() {
  const [messages, setMessages] = useState([
    {
      role: 'bot',
      text: 'Hello! 👋 I am your AI Assistant. What service do you need? (e.g. Plumber, Electrician, Coffee Order...)',
    },
  ]);
  const [input, setInput] = useState('');
  const [loading, setLoading] = useState(false);
  const [bookingDone, setBookingDone] = useState(false);
  const bottomRef = useRef(null);

  // Demo user — later replace with real login session
  const user_id = 1;
  const user_name = 'Friend';

  useEffect(() => {
    bottomRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages]);

  const sendMessage = async () => {
    if (!input.trim() || loading) return;

    const userText = input.trim();
    setInput('');
    setMessages((prev) => [...prev, { role: 'user', text: userText }]);
    setLoading(true);

    try {
      const res = await fetch(`${API_URL}/api_chat.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          user_id,
          user_name,
          message: userText,
        }),
      });

      const json = await res.json();

      if (json.reply) {
        setMessages((prev) => [...prev, { role: 'bot', text: json.reply }]);
      }

      if (json.booking_created && json.booking) {
        setBookingDone(true);
        setMessages((prev) => [
          ...prev,
          {
            role: 'system',
            text: `✅ Booking Confirmed! Code: ${json.booking.booking_code}`,
          },
        ]);
      }
    } catch (err) {
      setMessages((prev) => [
        ...prev,
        { role: 'bot', text: '❌ Server error. Please try again.' },
      ]);
    } finally {
      setLoading(false);
    }
  };

  const handleKey = (e) => {
    if (e.key === 'Enter') sendMessage();
  };

  return (
    <div className="flex flex-col h-screen bg-gray-950 text-white">
      {/* Header */}
      <div className="bg-gray-900 px-6 py-4 flex items-center gap-3 shadow-lg border-b border-gray-800">
        <div className="w-10 h-10 rounded-full bg-blue-600 flex items-center justify-center text-xl">🤖</div>
        <div>
          <h1 className="font-bold text-lg">AI Service Assistant</h1>
          <p className="text-xs text-green-400">● Online</p>
        </div>
      </div>

      {/* Messages */}
      <div className="flex-1 overflow-y-auto px-4 py-6 space-y-4">
        {messages.map((msg, i) => (
          <div key={i} className={`flex ${msg.role === 'user' ? 'justify-end' : 'justify-start'}`}>
            {msg.role === 'system' ? (
              <div className="mx-auto bg-green-800 text-green-100 px-4 py-2 rounded-xl text-sm font-semibold">
                {msg.text}
              </div>
            ) : (
              <div className={`max-w-xs md:max-w-md px-4 py-3 rounded-2xl text-sm shadow ${
                msg.role === 'user'
                  ? 'bg-blue-600 text-white rounded-br-none'
                  : 'bg-gray-800 text-gray-100 rounded-bl-none'
              }`}>
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
          className="flex-1 bg-gray-800 text-white rounded-full px-5 py-3 text-sm outline-none placeholder-gray-500 focus:ring-2 focus:ring-blue-500 disabled:opacity-50"
          placeholder="Type your service... (e.g. I need a plumber)"
          value={input}
          onChange={(e) => setInput(e.target.value)}
          onKeyDown={handleKey}
          disabled={loading || bookingDone}
        />
        <button
          onClick={sendMessage}
          disabled={loading || bookingDone}
          className="bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white rounded-full w-12 h-12 flex items-center justify-center text-xl transition"
        >
          ➤
        </button>
      </div>
    </div>
  );
}