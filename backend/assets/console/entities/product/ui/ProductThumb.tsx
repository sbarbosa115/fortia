import {Icon} from '@shared/ui';
import {useState} from 'react';
import './product.css';

/** A product's image, lazy-loaded, with an icon when it has none or it does not load. */
export function ProductThumb({
  imageUrl,
  name,
  size = 48,
}: {
  imageUrl: string | null | undefined;
  name: string;
  size?: number;
}) {
  const [broken, setBroken] = useState<string | null>(null);
  const showImage = Boolean(imageUrl) && broken !== imageUrl;
  return (
    <span className="product-thumb" style={{width: size, height: size}}>
      {showImage ? (
        <img
          src={imageUrl ?? undefined}
          alt={name}
          loading="lazy"
          onError={() => setBroken(imageUrl ?? null)}
        />
      ) : (
        <Icon name="file" size={Math.round(size / 2.4)} />
      )}
    </span>
  );
}
