import {describe, expect, it} from 'vitest';
import {
  domainMismatches,
  emptyMember,
  memberErrors,
  memberIdentity,
  normalizeMember,
  toPayload,
} from './members';

describe('organization members (PRD §6.13, §10.10)', () => {
  it('normalizes the name, email and phone the way the API stores them', () => {
    const member = normalizeMember({
      ...emptyMember(),
      name: '  José   PÉREZ ',
      email: ' Jose@ACME.test ',
      phone: ' +57 (300) 123-4567 ',
    });

    expect(member.name).toBe('jose perez');
    expect(member.email).toBe('jose@acme.test');
    expect(member.phone).toBe('+573001234567');
  });

  it('requires a name and an email or a phone', () => {
    const errors = memberErrors([{...emptyMember(), name: ' '}]);

    expect(errors[0]).toEqual({name: 'nameRequired', contact: 'contactRequired'});
  });

  it('refuses an invalid email', () => {
    const errors = memberErrors([
      {...emptyMember(), name: 'Ana', email: 'ana@'},
    ]);

    expect(errors[0]?.email).toBe('emailInvalid');
  });

  it('flags a second member with the same normalized email or phone', () => {
    const errors = memberErrors([
      {...emptyMember(), name: 'Ana', email: 'ana@acme.test'},
      {...emptyMember(), name: 'Ana 2', email: ' ANA@acme.test'},
      {...emptyMember(), name: 'Bruno', phone: '+57 300'},
      {...emptyMember(), name: 'Bruno 2', phone: '+57300'},
    ]);

    expect(errors[0]).toBeUndefined();
    expect(errors[1]?.duplicate).toBe('duplicate');
    expect(errors[3]?.duplicate).toBe('duplicate');
  });

  it('identifies a member by its email first, then its phone', () => {
    expect(
      memberIdentity({...emptyMember(), email: 'A@x.test', phone: '1'}),
    ).toEqual(['email:a@x.test', 'phone:1']);
    expect(memberIdentity({...emptyMember(), name: 'x'})).toEqual([]);
  });

  it('counts the members whose email is not on the organization domain', () => {
    const members = [
      {...emptyMember(), name: 'A', email: 'a@acme.test'},
      {...emptyMember(), name: 'B', email: 'b@other.test'},
      {...emptyMember(), name: 'C', phone: '1'},
    ];

    expect(domainMismatches(members, 'ACME.test')).toBe(1);
    expect(domainMismatches(members, '')).toBe(0);
  });

  it('sends existing members with their id and new ones without', () => {
    const payload = toPayload([
      {
        ...emptyMember(),
        organization_user_id: 'id-1',
        name: 'Ana',
        email: 'ana@x.test',
      },
      {...emptyMember(), name: 'Bruno', phone: '+1 555', role: ' Lead '},
    ]);

    expect(payload).toEqual([
      {
        organization_user_id: 'id-1',
        name: 'ana',
        email: 'ana@x.test',
        phone: null,
        role: null,
        area: null,
      },
      {
        name: 'bruno',
        email: null,
        phone: '+1555',
        role: 'Lead',
        area: null,
      },
    ]);
  });
});
