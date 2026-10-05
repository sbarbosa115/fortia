import {describe, expect, it} from 'vitest';
import {type ChatEntry, recentHistoryOf} from './useChat';

function conversation(length: number): ChatEntry[] {
  return Array.from({length}, (_, index) => ({
    id: index + 1,
    role: index % 2 === 0 ? 'user' : 'assistant',
    content: `message ${index + 1}`,
    time: '10:00',
  }));
}

describe('recentHistoryOf', () => {
  it('sends the whole conversation while it fits in 40 messages', () => {
    expect(
      recentHistoryOf(conversation(39)),
      'PRD §8.10: messages[] holds up to 40',
    ).toHaveLength(39);
  });

  it('drops the oldest messages once the conversation passes 40', () => {
    const sent = recentHistoryOf(conversation(45));

    expect(
      sent.length,
      'PRD §8.10: messages[] holds up to 40',
    ).toBeLessThanOrEqual(40);
    expect(sent.at(-1)?.content, 'the newest message is always sent').toBe(
      'message 45',
    );
    expect(
      sent.some((m) => m.content === 'message 1'),
      'the oldest message is dropped',
    ).toBe(false);
  });

  it('starts the window with a user message', () => {
    // 47 messages: the last 40 would start with an assistant answer (message 8).
    const sent = recentHistoryOf(conversation(47));

    expect(
      sent[0]?.role,
      'the models expect the conversation to open with the user',
    ).toBe('user');
    expect(sent[0]?.content).toBe('message 9');
    expect(sent).toHaveLength(39);
  });

  it('leaves the screen notes out', () => {
    const entries: ChatEntry[] = [
      ...conversation(2),
      {id: 99, role: 'note', content: 'Saved.', time: '10:00'},
    ];

    expect(
      recentHistoryOf(entries).map((m) => m.role),
      'notes are never sent to the assistant',
    ).toEqual(['user', 'assistant']);
  });
});
