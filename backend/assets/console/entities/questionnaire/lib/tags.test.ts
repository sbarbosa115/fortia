import {describe, expect, it} from 'vitest';
import {addTags, MAX_TAGS} from './tags';

describe('addTags', () => {
  it('adds trimmed tags, several at once separated by commas', () => {
    expect(addTags(['AP-03'], '  NP-12 ,  Two   words ,')).toEqual({
      tags: ['AP-03', 'NP-12', 'Two words'],
      error: null,
    });
  });

  it('skips a repeat whatever its case, keeping the first spelling', () => {
    expect(addTags(['AP-03'], 'ap-03').tags).toEqual(['AP-03']);
  });

  it('refuses a tag over 40 characters and a 21st tag', () => {
    expect(addTags([], 'a'.repeat(41))).toEqual({tags: [], error: 'tooLong'});
    const twenty = Array.from({length: MAX_TAGS}, (_, i) => `T${i}`);
    expect(addTags(twenty, 'one more')).toEqual({
      tags: twenty,
      error: 'tooMany',
    });
  });
});
