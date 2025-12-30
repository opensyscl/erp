import { useEffect } from 'react';

/**
 * Hook that prevents react-remove-scroll from adding margin-right/padding-right
 * to the body element when modals/dropdowns open.
 * This prevents layout shift when scroll lock is engaged.
 */
export function usePreventScrollLockShift() {
  useEffect(() => {
    const observer = new MutationObserver((mutations) => {
      mutations.forEach((mutation) => {
        if (mutation.type === 'attributes' && mutation.attributeName === 'style') {
          const body = document.body;
          const style = body.getAttribute('style');

          if (style && (style.includes('margin-right') || style.includes('padding-right'))) {
            // Remove margin-right and padding-right from inline styles
            const newStyle = style
              .replace(/margin-right:\s*\d+px\s*!important;?/gi, '')
              .replace(/padding-right:\s*\d+px\s*!important;?/gi, '')
              .replace(/margin-right:\s*\d+px;?/gi, '')
              .replace(/padding-right:\s*\d+px;?/gi, '')
              .trim();

            if (newStyle !== style) {
              body.setAttribute('style', newStyle || '');
            }
          }
        }
      });
    });

    observer.observe(document.body, {
      attributes: true,
      attributeFilter: ['style'],
    });

    return () => observer.disconnect();
  }, []);
}
