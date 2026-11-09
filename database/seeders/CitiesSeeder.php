<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\State;
use Illuminate\Database\Seeder;

class CitiesSeeder extends Seeder
{
    /**
     * Representative cities/towns per state (keyed by state code). Not
     * exhaustive — extend as operators need more coverage.
     */
    public function run(): void
    {
        $byState = [
            'AB' => ['Umuahia', 'Aba', 'Ohafia', 'Arochukwu', 'Bende'],
            'AD' => ['Yola', 'Jimeta', 'Mubi', 'Numan', 'Ganye'],
            'AK' => ['Uyo', 'Eket', 'Ikot Ekpene', 'Oron', 'Abak'],
            'AN' => ['Awka', 'Onitsha', 'Nnewi', 'Ekwulobia', 'Agulu'],
            'BA' => ['Bauchi', 'Azare', 'Misau', 'Jama\'are', 'Katagum'],
            'BY' => ['Yenagoa', 'Brass', 'Sagbama', 'Ogbia', 'Nembe'],
            'BE' => ['Makurdi', 'Gboko', 'Otukpo', 'Katsina-Ala', 'Adikpo'],
            'BO' => ['Maiduguri', 'Biu', 'Bama', 'Dikwa', 'Gwoza'],
            'CR' => ['Calabar', 'Ikom', 'Ogoja', 'Obudu', 'Ugep'],
            'DE' => ['Asaba', 'Warri', 'Sapele', 'Ughelli', 'Agbor'],
            'EB' => ['Abakaliki', 'Afikpo', 'Onueke', 'Ezza', 'Ishieke'],
            'ED' => ['Benin City', 'Auchi', 'Ekpoma', 'Uromi', 'Igarra'],
            'EK' => ['Ado-Ekiti', 'Ikere', 'Ikole', 'Ijero', 'Emure'],
            'EN' => ['Enugu', 'Nsukka', 'Oji River', 'Agbani', 'Awgu'],
            'FC' => ['Abuja', 'Gwagwalada', 'Kuje', 'Bwari', 'Kubwa', 'Nyanya'],
            'GO' => ['Gombe', 'Kumo', 'Billiri', 'Dukku', 'Kaltungo'],
            'IM' => ['Owerri', 'Orlu', 'Okigwe', 'Mbaise', 'Oguta'],
            'JI' => ['Dutse', 'Hadejia', 'Gumel', 'Birnin Kudu', 'Kazaure'],
            'KD' => ['Kaduna', 'Zaria', 'Kafanchan', 'Kagoro', 'Zonkwa'],
            'KN' => ['Kano', 'Wudil', 'Gwarzo', 'Rano', 'Bichi'],
            'KT' => ['Katsina', 'Funtua', 'Daura', 'Malumfashi', 'Dutsin-Ma'],
            'KE' => ['Birnin Kebbi', 'Argungu', 'Yauri', 'Zuru', 'Jega'],
            'KO' => ['Lokoja', 'Okene', 'Kabba', 'Idah', 'Anyigba'],
            'KW' => ['Ilorin', 'Offa', 'Omu-Aran', 'Jebba', 'Lafiagi'],
            'LA' => ['Ikeja', 'Lagos', 'Lekki', 'Ikorodu', 'Badagry', 'Epe', 'Ajah'],
            'NA' => ['Lafia', 'Keffi', 'Akwanga', 'Nasarawa', 'Karu'],
            'NI' => ['Minna', 'Bida', 'Suleja', 'Kontagora', 'Lapai'],
            'OG' => ['Abeokuta', 'Ijebu-Ode', 'Sagamu', 'Ota', 'Ilaro'],
            'ON' => ['Akure', 'Ondo', 'Owo', 'Okitipupa', 'Ikare'],
            'OS' => ['Osogbo', 'Ile-Ife', 'Ilesa', 'Ede', 'Iwo'],
            'OY' => ['Ibadan', 'Ogbomoso', 'Oyo', 'Iseyin', 'Saki'],
            'PL' => ['Jos', 'Bukuru', 'Pankshin', 'Shendam', 'Barkin Ladi'],
            'RI' => ['Port Harcourt', 'Bonny', 'Bori', 'Omoku', 'Ahoada'],
            'SO' => ['Sokoto', 'Tambuwal', 'Wurno', 'Illela', 'Gwadabawa'],
            'TA' => ['Jalingo', 'Wukari', 'Bali', 'Gembu', 'Takum'],
            'YO' => ['Damaturu', 'Potiskum', 'Gashua', 'Nguru', 'Geidam'],
            'ZA' => ['Gusau', 'Kaura Namoda', 'Talata Mafara', 'Anka', 'Bungudu'],
        ];

        $states = State::pluck('id', 'code');

        foreach ($byState as $code => $cities) {
            $stateId = $states[$code] ?? null;
            if (! $stateId) {
                continue;
            }

            foreach ($cities as $name) {
                City::updateOrCreate(
                    ['state_id' => $stateId, 'name' => $name],
                    ['state_id' => $stateId, 'name' => $name]
                );
            }
        }
    }
}
