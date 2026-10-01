import {describe, expect, it} from 'vitest';
import {
  audienceChip,
  audienceMembers,
  distinctValues,
  inAudience,
} from './audience';
import {
  buildRegistration,
  DEFAULT_REGISTRATION,
  registrationFrom,
  registrationValid,
} from './registration';

const members = [
  {
    organization_user_id: 'A-1',
    name: 'ana',
    role: 'Store Manager',
    area: 'Ventas',
  },
  {
    organization_user_id: 'b-2',
    name: 'luis',
    role: 'Driver',
    area: 'Operación',
  },
  {organization_user_id: 'c-3', name: 'sara', role: null, area: 'operacion'},
];

describe('audience (PRD §6.14)', () => {
  it('compares area and role ignoring case and accents', () => {
    expect(
      audienceMembers({type: 'area', values: ['OPERACION']}, members).map(
        (m) => m.name,
      ),
    ).toEqual(['luis', 'sara']);
    expect(
      inAudience({type: 'role', values: ['store manager']}, members[0]!),
    ).toBe(true);
  });

  it('matches chosen members by id whatever the case', () => {
    expect(inAudience({type: 'members', values: ['a-1']}, members[0]!)).toBe(
      true,
    );
    expect(inAudience({type: 'members', values: ['a-1']}, members[1]!)).toBe(
      false,
    );
  });

  it('lists the distinct areas with how many people each has', () => {
    expect(distinctValues(members, 'area')).toEqual([
      {value: 'Operación', count: 2},
      {value: 'Ventas', count: 1},
    ]);
    expect(distinctValues(members, 'role')).toEqual([
      {value: 'Driver', count: 1},
      {value: 'Store Manager', count: 1},
    ]);
  });

  it('summarizes the audience for its chip: Everybody, N people, Area: X +N', () => {
    expect(audienceChip({type: 'all', values: []})).toEqual({key: 'everybody'});
    expect(audienceChip({type: 'members', values: ['a', 'b']})).toEqual({
      key: 'people',
      count: 2,
    });
    expect(audienceChip({type: 'area', values: ['Sales', 'Ops']})).toEqual({
      key: 'area',
      first: 'Sales',
      more: 1,
    });
  });
});

describe('registration slide (PRD §10.11)', () => {
  it('builds name and email by default, both required', () => {
    const question = buildRegistration(DEFAULT_REGISTRATION, 'Sign in');
    expect(question).toMatchObject({
      category: 'user-capture-data',
      title: 'Sign in',
    });
    expect(question['options']).toEqual([
      {
        name: 'name',
        type: 'text',
        options: [],
        validations: [{type: 'required'}],
      },
      {
        name: 'email',
        type: 'email',
        options: [],
        validations: [{type: 'required'}],
      },
    ]);
  });

  it('needs email or phone visible and required', () => {
    expect(registrationValid(DEFAULT_REGISTRATION)).toBe(true);
    expect(
      registrationValid({
        ...DEFAULT_REGISTRATION,
        email: {visible: true, required: false},
      }),
    ).toBe(false);
    expect(
      registrationValid({
        ...DEFAULT_REGISTRATION,
        email: {visible: false, required: false},
        phone: {visible: true, required: true},
      }),
    ).toBe(true);
  });

  it('reads the settings back from a stored slide', () => {
    const settings = {
      ...DEFAULT_REGISTRATION,
      email: {visible: true, required: false},
      phone: {visible: true, required: true},
      area: {visible: true, required: false},
    };
    const stored = buildRegistration(settings, 'x') as {options: []};
    expect(registrationFrom([stored])).toEqual(settings);
    expect(registrationFrom([])).toEqual(DEFAULT_REGISTRATION);
  });
});
