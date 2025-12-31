import { useState } from 'react';

interface BlurImageProps {
    src: string;
    alt: string;
    className?: string;
    blur?: boolean;
}

export function BlurImage({ src, alt, className = '', blur = true }: BlurImageProps) {
    const [isLoaded, setIsLoaded] = useState(false);

    // If blur is disabled, render a simple image
    if (!blur) {
        return (
            <img
                src={src}
                alt={alt}
                loading="lazy"
                className={className}
            />
        );
    }

    return (
        <div className="relative w-full h-full">
            {/* Blur placeholder */}
            <div
                className={`absolute inset-0 bg-gradient-to-br from-gray-200 to-gray-300 transition-opacity duration-500 ${
                    isLoaded ? 'opacity-0' : 'opacity-100 animate-pulse'
                }`}
            />
            {/* Actual image */}
            <img
                src={src}
                alt={alt}
                loading="lazy"
                onLoad={() => setIsLoaded(true)}
                className={`${className} transition-all duration-500 ${
                    isLoaded ? 'opacity-100 blur-0' : 'opacity-0 blur-sm'
                }`}
            />
        </div>
    );
}
