import { SVGProps } from 'react';

type IconProps = SVGProps<SVGSVGElement> & {
    size?: number;
};

const defaultProps: IconProps = {
    size: 20,
    strokeWidth: 2,
    stroke: 'currentColor',
    fill: 'none',
};

// Products / Inventory
export const BoxIcon = ({ size = 20, ...props }: IconProps) => (
    <svg xmlns="http://www.w3.org/2000/svg" width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...props}>
        <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
        <polyline points="3.27 6.96 12 12.01 20.73 6.96"/>
        <line x1="12" y1="22.08" x2="12" y2="12"/>
    </svg>
);

// Image Off
export const ImageOffIcon = ({ size = 20, ...props }: IconProps) => (
    <svg xmlns="http://www.w3.org/2000/svg" width={size} height={size} viewBox="0 0 24 24" fill="none" className="injected-svg" data-src="https://cdn.hugeicons.com/icons/image-delete-02-twotone-rounded.svg?v=1.0.1" xmlns:xlink="http://www.w3.org/1999/xlink" role="img" color="#b1b1b1">
    <path opacity="0.4" d="M3 16L7.46967 11.5303C7.80923 11.1908 8.26978 11 8.75 11C9.23022 11 9.69077 11.1908 10.0303 11.5303L14 15.5M15.5 17L14 15.5M21 16L18.5303 13.5303C18.1908 13.1908 17.7302 13 17.25 13C16.7698 13 16.3092 13.1908 15.9697 13.5303L14 15.5" stroke="#b1b1b1" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
    <path d="M12 2.5C7.77027 2.5 5.6554 2.5 4.25276 3.69797C4.05358 3.86808 3.86808 4.05358 3.69797 4.25276C2.5 5.6554 2.5 7.77027 2.5 12C2.5 16.2297 2.5 18.3446 3.69797 19.7472C3.86808 19.9464 4.05358 20.1319 4.25276 20.302C5.6554 21.5 7.77027 21.5 12 21.5C16.2297 21.5 18.3446 21.5 19.7472 20.302C19.9464 20.1319 20.1319 19.9464 20.302 19.7472C21.5 18.3446 21.5 16.2297 21.5 12" stroke="#b1b1b1" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
    <path d="M21.5 8.5L18.5 5.5M18.5 5.5L15.5 2.5M18.5 5.5L21.5 2.5M18.5 5.5L15.5 8.5" stroke="#b1b1b1" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
    </svg>
);

// User Off / No Supplier
export const UserOffIcon = ({ size = 20, ...props }: IconProps) => (
    <svg xmlns="http://www.w3.org/2000/svg" width={size} height={size} viewBox="0 0 24 24" fill="none" className="injected-svg" data-src="https://cdn.hugeicons.com/icons/id-not-verified-stroke-rounded.svg?v=1.0.1" xmlns:xlink="http://www.w3.org/1999/xlink" role="img" color="#b1b1b1">
    <path d="M16.5 17L17.4989 18M17.4989 18L18.5 19M17.4989 18L18.5 17M17.4989 18L16.5 19M21.5 18C21.5 20.2091 19.7091 22 17.5 22C15.2909 22 13.5 20.2091 13.5 18C13.5 15.7909 15.2909 14 17.5 14C19.7091 14 21.5 15.7909 21.5 18Z" stroke="#b1b1b1" stroke-width="1.5" stroke-linecap="round"></path>
    <path d="M6.95309 17.4868C6.66965 17.7888 6.68474 18.2635 6.98679 18.5469C7.28884 18.8304 7.76348 18.8153 8.04691 18.5132L6.95309 17.4868ZM10.5791 17.2458C10.991 17.2021 11.2895 16.8328 11.2458 16.4209C11.2021 16.009 10.8328 15.7105 10.4209 15.7542L10.5791 17.2458ZM3.52513 21.0288L3.00932 21.5733L3.52513 21.0288ZM18.4749 4.97116L17.9591 5.51562L18.4749 4.97116ZM3.52513 4.97116L4.04093 5.51562L3.52513 4.97116ZM8.50279 4.75115C8.91701 4.74961 9.25154 4.41258 9.24999 3.99836C9.24845 3.58415 8.91142 3.24962 8.49721 3.25116L8.50279 4.75115ZM13.5028 3.25116C13.0886 3.24962 12.7515 3.58415 12.75 3.99836C12.7485 4.41258 13.083 4.74961 13.4972 4.75115L13.5028 3.25116ZM18.75 12C18.75 12.4142 19.0858 12.75 19.5 12.75C19.9142 12.75 20.25 12.4142 20.25 12H18.75ZM10.5 22.75C10.9142 22.75 11.25 22.4142 11.25 22C11.25 21.5858 10.9142 21.25 10.5 21.25V22.75ZM8.96901 5.82911L9.35835 5.18808L8.96901 5.82911ZM8.68886 3.99176L7.95815 3.82274V3.82274L8.68886 3.99176ZM8.56197 5.37824L7.87896 5.68808L8.56197 5.37824ZM13.3111 3.99176L14.0418 3.82274L13.3111 3.99176ZM13.438 5.37824L14.121 5.68808L13.438 5.37824ZM13.031 5.82911L13.4203 6.47014L13.031 5.82911ZM11.9086 2.0467L11.7403 2.77759L11.9086 2.0467ZM13.2278 3.63163L12.4971 3.80065L13.2278 3.63163ZM12.9918 2.84004L12.333 3.19842L12.9918 2.84004ZM10.0914 2.0467L10.2597 2.77759L10.0914 2.0467ZM8.77216 3.63163L9.50286 3.80065V3.80065L8.77216 3.63163ZM9.00821 2.84004L9.66705 3.19842L9.00821 2.84004ZM12.25 12C12.25 12.7016 11.6933 13.25 11.0315 13.25V14.75C12.5441 14.75 13.75 13.5075 13.75 12H12.25ZM11.0315 13.25C10.3698 13.25 9.81307 12.7016 9.81307 12H8.31307C8.31307 13.5075 9.51898 14.75 11.0315 14.75V13.25ZM9.81307 12C9.81307 11.2984 10.3698 10.75 11.0315 10.75V9.25C9.51898 9.25 8.31307 10.4925 8.31307 12H9.81307ZM11.0315 10.75C11.6933 10.75 12.25 11.2984 12.25 12H13.75C13.75 10.4925 12.5441 9.25 11.0315 9.25V10.75ZM8.04691 18.5132C8.72491 17.7907 9.62319 17.3472 10.5791 17.2458L10.4209 15.7542C9.10487 15.8938 7.87535 16.504 6.95309 17.4868L8.04691 18.5132ZM3.25 15.3684V10.6316H1.75V15.3684H3.25ZM9.5 21.25C7.82998 21.25 6.64898 21.2486 5.75431 21.1346C4.87725 21.0229 4.38967 20.8147 4.04093 20.4844L3.00932 21.5733C3.68571 22.2141 4.53565 22.4915 5.56479 22.6226C6.57633 22.7514 7.8702 22.75 9.5 22.75V21.25ZM1.75 15.3684C1.75 16.9091 1.74823 18.1443 1.88558 19.1121C2.02661 20.1059 2.32721 20.9271 3.00932 21.5733L4.04093 20.4844C3.69792 20.1594 3.48595 19.7135 3.3707 18.9013C3.25177 18.0633 3.25 16.9538 3.25 15.3684H1.75ZM20.25 10.6316C20.25 9.09083 20.2518 7.8557 20.1144 6.88786C19.9734 5.8941 19.6728 5.0729 18.9907 4.42669L17.9591 5.51562C18.3021 5.84058 18.514 6.28651 18.6293 7.09862C18.7482 7.93665 18.75 9.04614 18.75 10.6316H20.25ZM3.25 10.6316C3.25 9.04614 3.25177 7.93665 3.3707 7.09862C3.48595 6.28651 3.69792 5.84058 4.04093 5.51562L3.00932 4.42669C2.32721 5.0729 2.02661 5.8941 1.88558 6.88786C1.74823 7.8557 1.75 9.09083 1.75 10.6316H3.25ZM8.49721 3.25116C7.18295 3.25606 6.10891 3.28037 5.24135 3.42463C4.35533 3.57196 3.61291 3.85487 3.00932 4.42669L4.04093 5.51562C4.35322 5.21977 4.77604 5.0226 5.48739 4.90432C6.2172 4.78296 7.17265 4.75611 8.50279 4.75115L8.49721 3.25116ZM13.4972 4.75115C14.8273 4.75611 15.7828 4.78296 16.5126 4.90432C17.224 5.0226 17.6468 5.21977 17.9591 5.51562L18.9907 4.42669C18.3871 3.85487 17.6447 3.57196 16.7587 3.42463C15.8911 3.28037 14.817 3.25606 13.5028 3.25116L13.4972 4.75115ZM18.75 10.6316V12H20.25V10.6316H18.75ZM10.5 21.25H9.5V22.75H10.5V21.25ZM12.4971 3.80065L12.5804 4.16077L14.0418 3.82274L13.9586 3.46262L12.4971 3.80065ZM11.4981 5.25H10.5019V6.75H11.4981V5.25ZM9.41957 4.16077L9.50286 3.80065L8.04145 3.46262L7.95815 3.82274L9.41957 4.16077ZM10.5019 5.25C10.071 5.25 9.7969 5.24932 9.59356 5.2315C9.49716 5.22305 9.43739 5.2122 9.4 5.2024C9.38224 5.19774 9.37137 5.19379 9.3655 5.19139C9.36256 5.19019 9.36069 5.1893 9.35969 5.1888C9.35918 5.18854 9.35883 5.18835 9.35863 5.18824C9.35844 5.18813 9.35835 5.18808 9.35835 5.18808L8.57968 6.47014C8.86483 6.64332 9.17387 6.70047 9.46262 6.72578C9.74679 6.75068 10.0984 6.75 10.5019 6.75V5.25ZM7.95815 3.82274C7.87686 4.17417 7.80134 4.49614 7.76888 4.7648C7.73518 5.04374 7.73287 5.36605 7.87896 5.68808L9.24497 5.06839C9.2627 5.10747 9.23876 5.10438 9.25805 4.94474C9.27858 4.77482 9.33069 4.54503 9.41957 4.16077L7.95815 3.82274ZM9.35835 5.18808C9.29699 5.15081 9.26197 5.10585 9.24497 5.06839L7.87896 5.68808C8.02824 6.01716 8.27656 6.28603 8.57968 6.47014L9.35835 5.18808ZM12.5804 4.16077C12.6693 4.54503 12.7214 4.77482 12.742 4.94474C12.7612 5.10438 12.7373 5.10747 12.755 5.06839L14.121 5.68808C14.2671 5.36605 14.2648 5.04374 14.2311 4.7648C14.1987 4.49614 14.1231 4.17417 14.0418 3.82274L12.5804 4.16077ZM11.4981 6.75C11.9016 6.75 12.2532 6.75068 12.5374 6.72578C12.8261 6.70047 13.1352 6.64332 13.4203 6.47014L12.6417 5.18808C12.6417 5.18808 12.6416 5.18813 12.6414 5.18824C12.6412 5.18835 12.6408 5.18854 12.6403 5.1888C12.6393 5.1893 12.6374 5.19019 12.6345 5.19139C12.6286 5.19379 12.6178 5.19774 12.6 5.2024C12.5626 5.2122 12.5028 5.22305 12.4064 5.2315C12.2031 5.24932 11.929 5.25 11.4981 5.25V6.75ZM12.755 5.06839C12.738 5.10585 12.703 5.15081 12.6417 5.18808L13.4203 6.47014C13.7234 6.28604 13.9718 6.01716 14.121 5.68808L12.755 5.06839ZM11 2.75C11.5097 2.75 11.6409 2.7547 11.7403 2.77759L12.0768 1.3158C11.7704 1.2453 11.4312 1.25 11 1.25V2.75ZM13.9586 3.46262C13.8729 3.09244 13.8035 2.76264 13.6506 2.48165L12.333 3.19842C12.3644 3.25619 12.3909 3.3412 12.4971 3.80065L13.9586 3.46262ZM11.7403 2.77759C12.0225 2.84252 12.2298 3.00886 12.333 3.19842L13.6506 2.48165C13.3245 1.88205 12.74 1.46844 12.0768 1.3158L11.7403 2.77759ZM11 1.25C10.5688 1.25 10.2296 1.2453 9.92323 1.3158L10.2597 2.77759C10.3591 2.7547 10.4903 2.75 11 2.75V1.25ZM9.50286 3.80065C9.60914 3.3412 9.63562 3.25619 9.66705 3.19842L8.34938 2.48165C8.19653 2.76264 8.12707 3.09244 8.04145 3.46262L9.50286 3.80065ZM9.92323 1.3158C9.26005 1.46844 8.67555 1.88205 8.34938 2.48165L9.66705 3.19842C9.77016 3.00886 9.97753 2.84252 10.2597 2.77759L9.92323 1.3158Z" fill="#b1b1b1"></path>
    <path d="M7.5 18C8.41684 17.0229 9.72299 16.5115 11.0315 16.5002M13 12C13 13.1046 12.1187 14 11.0315 14C9.94438 14 9.06307 13.1046 9.06307 12C9.06307 10.8954 9.94438 10 11.0315 10C12.1187 10 13 10.8954 13 12Z" stroke="#b1b1b1" stroke-width="1.5" stroke-linecap="round"></path>
    <path d="M8.5 4.00195C5.85561 4.01181 4.44101 4.10427 3.52513 4.97195C2.5 5.94312 2.5 7.5062 2.5 10.6324V15.3692C2.5 18.4954 2.5 20.0584 3.52513 21.0296C4.55025 22.0008 6.20017 22.0008 9.5 22.0008H11.5M13.5 4.00195C16.1444 4.01181 17.559 4.10427 18.4749 4.97195C19.5 5.94312 19.5 7.5062 19.5 10.6324V11.5008" stroke="#b1b1b1" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
    <path d="M8.77216 3.63163C8.8681 3.21682 8.91608 3.00942 9.00821 2.84004C9.22285 2.44546 9.61879 2.15548 10.0914 2.0467C10.2943 2 10.5296 2 11 2C11.4704 2 11.7057 2 11.9086 2.0467C12.3812 2.15548 12.7771 2.44545 12.9918 2.84004C13.0839 3.00942 13.1319 3.21682 13.2278 3.63163L13.3111 3.99176C13.4813 4.72744 13.5664 5.09528 13.438 5.37824C13.3549 5.5615 13.2132 5.71842 13.031 5.82911C12.7496 6 12.3324 6 11.4981 6H10.5019C9.66755 6 9.25038 6 8.96901 5.82911C8.78677 5.71842 8.6451 5.5615 8.56197 5.37824C8.43361 5.09528 8.51869 4.72744 8.68886 3.99176L8.77216 3.63163Z" stroke="#b1b1b1" stroke-width="1.5"></path>
    </svg>
);

// Alert Triangle / Negative Stock
export const AlertTriangleIcon = ({ size = 20, ...props }: IconProps) => (
    <svg xmlns="http://www.w3.org/2000/svg" width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...props}>
    <path d="M15.5 2V5M6.5 2V5M11 2V5" stroke="#b1b1b1" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
    <path d="M19 14V5.5C19 4.39543 18.1046 3.5 17 3.5H5C3.89543 3.5 3 4.39543 3 5.5V20C3 21.1046 3.89543 22 5 22H13" stroke="#b1b1b1" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
    <path d="M21 17L18.5 19.5M18.5 19.5L16 22M18.5 19.5L21 22M18.5 19.5L16 17" stroke="#b1b1b1" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
    <path d="M7 15H11M7 11H15" stroke="#b1b1b1" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
    </svg>
);

// Archive
export const ArchiveIcon = ({ size = 20, ...props }: IconProps) => (
    <svg xmlns="http://www.w3.org/2000/svg" width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...props}>
    <path d="M13 2H11C7.22876 2 5.34315 2 4.17157 3.17157C3 4.34315 3 6.22876 3 10V14C3 17.7712 3 19.6569 4.17157 20.8284C5.34315 22 7.22876 22 11 22H13C16.7712 22 18.6569 22 19.8284 20.8284C21 19.6569 21 17.7712 21 14V10C21 6.22876 21 4.34315 19.8284 3.17157C18.6569 2 16.7712 2 13 2Z" stroke="#b1b1b1" stroke-width="1.5" stroke-linecap="round"></path>
    <path opacity="0.4" d="M21 12H3" stroke="#b1b1b1" stroke-width="1.5" stroke-linecap="round"></path>
    <path opacity="0.4" d="M15 7H9" stroke="#b1b1b1" stroke-width="1.5" stroke-linecap="round"></path>
    <path opacity="0.4" d="M15 17H9" stroke="#b1b1b1" stroke-width="1.5" stroke-linecap="round"></path>
    </svg>
    );

// Stock Low / Warning
export const PackageMinusIcon = ({ size = 20, ...props }: IconProps) => (
    <svg xmlns="http://www.w3.org/2000/svg" width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...props}>
        <path d="M16 16h6"/>
        <path d="M21 10V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l2-1.14"/>
        <path d="m7.5 4.27 9 5.15"/>
        <polyline points="3.29 7 12 12 20.71 7"/>
        <line x1="12" y1="22" x2="12" y2="12"/>
    </svg>
);

// Out of Stock
export const PackageXIcon = ({ size = 20, ...props }: IconProps) => (
    <svg xmlns="http://www.w3.org/2000/svg" width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...props}>
        <path d="M21 10V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l2-1.14"/>
        <path d="m7.5 4.27 9 5.15"/>
        <polyline points="3.29 7 12 12 20.71 7"/>
        <line x1="12" y1="22" x2="12" y2="12"/>
        <path d="m17 13 5 5m-5 0 5-5"/>
    </svg>
);

// Store / Supplier
export const StoreIcon = ({ size = 20, ...props }: IconProps) => (
    <svg xmlns="http://www.w3.org/2000/svg" width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...props}>
        <path d="m2 7 4.41-4.41A2 2 0 0 1 7.83 2h8.34a2 2 0 0 1 1.42.59L22 7"/>
        <path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/>
        <path d="M15 22v-4a2 2 0 0 0-2-2h-2a2 2 0 0 0-2 2v4"/>
        <path d="M2 7h20"/>
        <path d="M22 7v3a2 2 0 0 1-2 2v0a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 16 12a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 12 12a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 8 12a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 4 12v0a2 2 0 0 1-2-2V7"/>
    </svg>
);

// Category / Tag
export const TagIcon = ({ size = 20, ...props }: IconProps) => (
    <svg xmlns="http://www.w3.org/2000/svg" width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...props}>
        <path d="M12 2H2v10l9.29 9.29c.94.94 2.48.94 3.42 0l6.58-6.58c.94-.94.94-2.48 0-3.42L12 2Z"/>
        <path d="M7 7h.01"/>
    </svg>
);

// Dollar / Price
export const DollarIcon = ({ size = 20, ...props }: IconProps) => (
    <svg xmlns="http://www.w3.org/2000/svg" width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...props}>
        <line x1="12" y1="1" x2="12" y2="23"/>
        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
    </svg>
);

// Barcode
export const BarcodeIcon = ({ size = 20, ...props }: IconProps) => (
    <svg xmlns="http://www.w3.org/2000/svg" width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...props}>
        <path d="M3 5v14"/>
        <path d="M8 5v14"/>
        <path d="M12 5v14"/>
        <path d="M17 5v14"/>
        <path d="M21 5v14"/>
    </svg>
);

// TrendingDown / Loss
export const TrendingDownIcon = ({ size = 20, ...props }: IconProps) => (
    <svg xmlns="http://www.w3.org/2000/svg" width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...props}>
        <polyline points="23 18 13.5 8.5 8.5 13.5 1 6"/>
        <polyline points="17 18 23 18 23 12"/>
    </svg>
);

// Chart / Analytics
export const ChartIcon = ({ size = 20, ...props }: IconProps) => (
    <svg xmlns="http://www.w3.org/2000/svg" width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...props}>
        <line x1="18" y1="20" x2="18" y2="10"/>
        <line x1="12" y1="20" x2="12" y2="4"/>
        <line x1="6" y1="20" x2="6" y2="14"/>
    </svg>
);

// Clipboard / Stock Count
export const ClipboardIcon = ({ size = 20, ...props }: IconProps) => (
    <svg xmlns="http://www.w3.org/2000/svg" width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...props}>
        <rect x="8" y="2" width="8" height="4" rx="1" ry="1"/>
        <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/>
        <path d="M12 11h4"/>
        <path d="M12 16h4"/>
        <path d="M8 11h.01"/>
        <path d="M8 16h.01"/>
    </svg>
);


export const ProductItemIcon = ({ size = 20, ...props }: IconProps) => (
    <svg xmlns="http://www.w3.org/2000/svg" width={size} height={size} viewBox="0 0 24 24" fill="none" {...props}>
        <path d="M4.5 10.2653V6H19.5V10.2653C19.5 13.4401 19.5 15.0275 18.5237 16.0137C17.5474 17 15.976 17 12.8333 17H11.1667C8.02397 17 6.45262 17 5.47631 16.0137C4.5 15.0275 4.5 13.4401 4.5 10.2653Z" stroke="#b1b1b1" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
        <path d="M4.5 6L5.22115 4.46154C5.78045 3.26838 6.06009 2.6718 6.62692 2.3359C7.19375 2 7.92084 2 9.375 2H14.625C16.0792 2 16.8062 2 17.3731 2.3359C17.9399 2.6718 18.2196 3.26838 18.7788 4.46154L19.5 6" stroke="#b1b1b1" stroke-width="1.5" stroke-linecap="round"></path>
        <path d="M10.5 9H13.5" stroke="#b1b1b1" stroke-width="1.5" stroke-linecap="round"></path>
        <path d="M12 19.5V22M12 19.5L7 19.5M12 19.5H17M7 19.5H4.5C3.11929 19.5 2 20.6193 2 22M7 19.5V22M17 19.5H19.5C20.8807 19.5 22 20.6193 22 22M17 19.5V22" stroke="#b1b1b1" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
    </svg>
)

export const CashIcon = ({ size = 20, ...props }: IconProps) => (
     <svg xmlns="http://www.w3.org/2000/svg" width={size} height={size} viewBox="0 0 24 24" fill="none" {...props}>
        <path d="M14.5005 9.5C14.5005 10.8807 13.3812 12 12.0005 12C10.6198 12 9.50049 10.8807 9.50049 9.5C9.50049 8.11929 10.6198 7 12.0005 7C13.3812 7 14.5005 8.11929 14.5005 9.5Z" stroke="#b1b1b1" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
        <path d="M16 2.5C18.7632 2.5 20.572 2.9772 21.4264 3.2723C21.7844 3.39596 22 3.73926 22 4.11803V14.9913C22 15.7347 21.1888 16.2796 20.4671 16.1012C19.4672 15.854 17.9782 15.6094 16 15.6094C11.1629 15.6094 10.0694 17.4812 2.75993 15.7923C2.31284 15.689 2 15.2875 2 14.8286V3.78078C2 3.1302 2.61507 2.6548 3.25078 2.79306C10.1213 4.28736 11.2733 2.5 16 2.5Z" stroke="#b1b1b1" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
        <path d="M2 6.5C3.95133 6.5 5.70483 4.90507 5.92901 3.25417M18.5005 3C18.5005 5.03964 20.2655 6.96899 22 6.96899M22 12.5C20.1009 12.5 18.2601 13.8102 18.102 15.5983M6.00049 15.9961C6.00049 13.787 4.20963 11.9961 2.00049 11.9961" stroke="#b1b1b1" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
        <path d="M19 18.7329C18.1717 18.5965 17.1718 18.5 16.0005 18.5C11.7061 18.5 10.3624 20.1598 5 19.2027" stroke="#b1b1b1" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
    </svg>
)
