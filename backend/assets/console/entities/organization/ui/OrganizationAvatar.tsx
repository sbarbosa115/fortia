import {avatarColor, initialOf} from '../lib/avatar';
import './organization.css';

/** The organization's initial on a colour derived from its name (decorative: the name is next to it). */
export function OrganizationAvatar({
  name,
  size = 44,
}: {
  name: string;
  size?: number;
}) {
  return (
    <span
      className="org-avatar"
      aria-hidden
      style={{
        background: avatarColor(name),
        width: size,
        height: size,
        fontSize: Math.round(size * 0.45),
      }}
    >
      {initialOf(name)}
    </span>
  );
}
