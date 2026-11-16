<?php

namespace Database\Seeders;

use App\Models\Vehiculo;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Fotos reales de los vehículos de ejemplo.
 *
 * Fuente: Wikimedia Commons, solo archivos con licencia libre (CC0, CC BY, CC BY-SA o dominio público).
 * Se guarda la miniatura (upload.wikimedia.org/.../thumb/...). El autor y la licencia de cada foto
 * figuran en su página de Commons: https://commons.wikimedia.org/wiki/File:<nombre del archivo>
 *
 * Al ejecutarlo NO se descarga nada: las URLs están fijas en el array FOTOS.
 * Uso: php artisan db:seed --class=FotosVehiculosSeeder
 */
class FotosVehiculosSeeder extends Seeder
{
    // "Marca Modelo" => entre 2 y 4 URLs (frente, atrás y otros ángulos)
    const FOTOS = [
        'Toyota Corolla' => [
            'https://upload.wikimedia.org/wikipedia/commons/thumb/4/40/Toyota_Corolla_%28E210%29_Washington_DC_Metro_Area%2C_USA.jpg/1280px-Toyota_Corolla_%28E210%29_Washington_DC_Metro_Area%2C_USA.jpg', // CC BY 4.0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/f/f1/2018_Toyota_Corolla_%28MZEA12R%29_Ascent_Sport_hatchback_%282018-11-02%29_01.jpg/1280px-2018_Toyota_Corolla_%28MZEA12R%29_Ascent_Sport_hatchback_%282018-11-02%29_01.jpg', // CC BY-SA 4.0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/8/87/Toyota_Corolla_E150_sedan_facelift_Shishi_01_2021-12-24.jpg/1280px-Toyota_Corolla_E150_sedan_facelift_Shishi_01_2021-12-24.jpg', // CC BY-SA 4.0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/c/cc/2018_Toyota_Corolla_Altis_1.6_G_20221015.jpg/1280px-2018_Toyota_Corolla_Altis_1.6_G_20221015.jpg', // CC BY-SA 4.0
        ],
        'Toyota Yaris' => [
            'https://upload.wikimedia.org/wikipedia/commons/thumb/3/3e/2020_Toyota_Yaris_Design_HEV_CVT_1.5_Front.jpg/1280px-2020_Toyota_Yaris_Design_HEV_CVT_1.5_Front.jpg', // CC BY-SA 4.0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/e/e3/Toyota_Yaris_1.3_VVT-i_rear.JPG/1280px-Toyota_Yaris_1.3_VVT-i_rear.JPG', // CC BY-SA 3.0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/2/2b/2019_Toyota_Yaris_1.5_E_%28108%29.jpg/1280px-2019_Toyota_Yaris_1.5_E_%28108%29.jpg', // CC BY-SA 4.0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/1/1a/Toyota_Yaris_L_hatch_facelift_Shishi_02_2022-04-23.jpg/1280px-Toyota_Yaris_L_hatch_facelift_Shishi_02_2022-04-23.jpg', // CC BY-SA 4.0
        ],
        'Toyota Hilux' => [
            'https://upload.wikimedia.org/wikipedia/commons/thumb/1/1b/2016_Toyota_HiLux_Invincible_D-4D_4WD_2.4_Front.jpg/1280px-2016_Toyota_HiLux_Invincible_D-4D_4WD_2.4_Front.jpg', // CC BY-SA 4.0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/9/97/Toyota_Hilux_4x2_G_%28CAA-9105%29_Rear.jpg/1280px-Toyota_Hilux_4x2_G_%28CAA-9105%29_Rear.jpg', // CC BY-SA 4.0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/0/03/2016_Toyota_HiLux_%28GUN136R%29_SR5_4-door_utility_%282018-09-03%29_01.jpg/1280px-2016_Toyota_HiLux_%28GUN136R%29_SR5_4-door_utility_%282018-09-03%29_01.jpg', // CC BY-SA 4.0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/e/e1/2024_Toyota_Hilux_2.8_GR_Sport_4x4_in_Attitude_Black_Mica%2C_front_right%2C_06-27-2024.jpg/1280px-2024_Toyota_Hilux_2.8_GR_Sport_4x4_in_Attitude_Black_Mica%2C_front_right%2C_06-27-2024.jpg', // CC BY-SA 4.0
        ],
        'Toyota RAV4' => [
            'https://upload.wikimedia.org/wikipedia/commons/thumb/4/4b/2018_Toyota_RAV4_2.0_CVT_4WD_Adventure_front.jpg/1280px-2018_Toyota_RAV4_2.0_CVT_4WD_Adventure_front.jpg', // CC0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/f/f8/2018_Toyota_RAV4_25th_Anniversary_white_rear.jpg/1280px-2018_Toyota_RAV4_25th_Anniversary_white_rear.jpg', // CC0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/7/78/Toyota_RAV4_Hybrid%2C_Paris_Motor_Show_2018%2C_IMG_0386.jpg/1280px-Toyota_RAV4_Hybrid%2C_Paris_Motor_Show_2018%2C_IMG_0386.jpg', // CC BY-SA 4.0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/a/a8/2019_Toyota_RAV4_Hybrid_01.jpg/1280px-2019_Toyota_RAV4_Hybrid_01.jpg', // CC BY-SA 4.0
        ],
        'Volkswagen Gol' => [
            'https://upload.wikimedia.org/wikipedia/commons/thumb/6/69/Volkswagen_Gol_Hatchback_--_Front.JPG/1280px-Volkswagen_Gol_Hatchback_--_Front.JPG', // Public domain
            'https://upload.wikimedia.org/wikipedia/commons/thumb/f/fb/Volkswagen_Gol_II_facelift_01_China_2012-04-08.jpg/1280px-Volkswagen_Gol_II_facelift_01_China_2012-04-08.jpg', // CC BY-SA 3.0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/c/c1/Volkswagen_Gol_1.6_Power_2013_%2812489173025%29.jpg/1280px-Volkswagen_Gol_1.6_Power_2013_%2812489173025%29.jpg', // CC BY-SA 2.0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/3/30/Volkswagen_Gol_Trend_1.6_Pack_I_3p_2013.jpg/1280px-Volkswagen_Gol_Trend_1.6_Pack_I_3p_2013.jpg', // CC BY 4.0
        ],
        'Volkswagen Polo' => [
            'https://upload.wikimedia.org/wikipedia/commons/thumb/9/94/2018_Volkswagen_Polo_SE_TSi_1.0_Front.jpg/1280px-2018_Volkswagen_Polo_SE_TSi_1.0_Front.jpg', // CC BY-SA 4.0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/9/92/VW_Polo_Genf_2018.jpg/1280px-VW_Polo_Genf_2018.jpg', // CC BY-SA 4.0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/4/4a/2018_Volkswagen_Polo_R-Line_TSi_1.0_Front.jpg/1280px-2018_Volkswagen_Polo_R-Line_TSi_1.0_Front.jpg', // CC BY-SA 4.0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/a/aa/Volkswagen_Polo_2019.jpg/1280px-Volkswagen_Polo_2019.jpg', // CC BY-SA 4.0
        ],
        'Volkswagen Golf' => [
            'https://upload.wikimedia.org/wikipedia/commons/thumb/5/59/2013_Volkswagen_Golf_SE_BlueMotion_Technology_1.4_Front.jpg/1280px-2013_Volkswagen_Golf_SE_BlueMotion_Technology_1.4_Front.jpg', // CC BY-SA 4.0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/5/5c/2017_Volkswagen_Golf_%285G_MY17%29_1.4_SE_TSI_hatchback_%282017-08-30%29.jpg/1280px-2017_Volkswagen_Golf_%285G_MY17%29_1.4_SE_TSI_hatchback_%282017-08-30%29.jpg', // CC BY 2.0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/8/8a/2020_Volkswagen_Golf_Style_1.5_Front.jpg/1280px-2020_Volkswagen_Golf_Style_1.5_Front.jpg', // CC BY-SA 4.0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/0/0f/Volkswagen_Golf_Mk7_GTS_2.0_TDI_Business_Edition_Pure_White_02.jpg/1280px-Volkswagen_Golf_Mk7_GTS_2.0_TDI_Business_Edition_Pure_White_02.jpg', // CC BY-SA 4.0
        ],
        'Volkswagen Amarok' => [
            'https://upload.wikimedia.org/wikipedia/commons/thumb/4/44/2019_Volkswagen_Amarok_Highline_V6_TDi_4MOTION_3.0_Front.jpg/1280px-2019_Volkswagen_Amarok_Highline_V6_TDi_4MOTION_3.0_Front.jpg', // CC BY-SA 4.0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/8/80/2017_Volkswagen_Amarok_Highline_V6_TDi_facelift_3.0.jpg/1280px-2017_Volkswagen_Amarok_Highline_V6_TDi_facelift_3.0.jpg', // CC BY-SA 4.0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/c/cf/Volkswagen_Amarok_V6%2C_Auto_2024%2C_Zurich_%28PANA0869%29.jpg/1280px-Volkswagen_Amarok_V6%2C_Auto_2024%2C_Zurich_%28PANA0869%29.jpg', // CC BY-SA 4.0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/f/f9/VW_Amarok_DC_Life_2.0I_TDI_IAA_2022_%28DSC04079%29.jpg/1280px-VW_Amarok_DC_Life_2.0I_TDI_IAA_2022_%28DSC04079%29.jpg', // CC BY-SA 4.0
        ],
        'Chevrolet Onix' => [
            'https://upload.wikimedia.org/wikipedia/commons/thumb/f/fa/2019_SAIC-GM_Chevrolet_Onix_%28front%29.jpg/1280px-2019_SAIC-GM_Chevrolet_Onix_%28front%29.jpg', // CC BY-SA 4.0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/8/88/2019_SAIC-GM_Chevrolet_Onix_%28rear%29.jpg/1280px-2019_SAIC-GM_Chevrolet_Onix_%28rear%29.jpg', // CC BY-SA 4.0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/1/17/Chevrolet_Onix_Activ_2017_%2849170997611%29.jpg/1280px-Chevrolet_Onix_Activ_2017_%2849170997611%29.jpg', // CC BY-SA 2.0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/0/0b/2020_SAIC-GM_Chevrolet_Onix_sedan.jpg/1280px-2020_SAIC-GM_Chevrolet_Onix_sedan.jpg', // CC BY-SA 4.0
        ],
        'Chevrolet Cruze' => [
            'https://upload.wikimedia.org/wikipedia/commons/thumb/5/58/2019_Chevrolet_Cruze_LT_USDM_front.jpg/1280px-2019_Chevrolet_Cruze_LT_USDM_front.jpg', // CC BY 2.0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/8/89/Chevrolet_Cruze_Back_P4250805.jpg/1280px-Chevrolet_Cruze_Back_P4250805.jpg', // CC BY-SA 4.0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/6/60/Chevrolet_Cruze_J300_hatch_Shishi_01_2022-04-26.jpg/1280px-Chevrolet_Cruze_J300_hatch_Shishi_01_2022-04-26.jpg', // CC BY-SA 4.0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/0/08/Chevrolet_Cruze_J300_hatch_Shishi_02_2022-04-26.jpg/1280px-Chevrolet_Cruze_J300_hatch_Shishi_02_2022-04-26.jpg', // CC BY-SA 4.0
        ],
        'Chevrolet Tracker' => [
            'https://upload.wikimedia.org/wikipedia/commons/thumb/6/66/2022_Chevrolet_Tracker_1.2_Turbo_LTZ%2C_front_%28Argentina%29.jpg/1280px-2022_Chevrolet_Tracker_1.2_Turbo_LTZ%2C_front_%28Argentina%29.jpg', // CC BY 4.0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/1/1d/2022_Chevrolet_Tracker_1.2_Turbo_LTZ%2C_rear_%28Argentina%29.jpg/1280px-2022_Chevrolet_Tracker_1.2_Turbo_LTZ%2C_rear_%28Argentina%29.jpg', // CC BY 4.0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/3/33/2022_Chevrolet_Tracker_1.2_Turbo_LS.jpg/1280px-2022_Chevrolet_Tracker_1.2_Turbo_LS.jpg', // CC BY-SA 2.0
        ],
        'Chevrolet S10' => [
            'https://upload.wikimedia.org/wikipedia/commons/thumb/3/38/2017_Chevrolet_S-10_2.8_4X2_LS_rear_view.jpg/1280px-2017_Chevrolet_S-10_2.8_4X2_LS_rear_view.jpg', // CC BY-SA 4.0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/e/e6/Chevrolet_S10_Bariloche_%282%29.jpg/1280px-Chevrolet_S10_Bariloche_%282%29.jpg', // CC BY 4.0
        ],
        'Ford Fiesta' => [
            'https://upload.wikimedia.org/wikipedia/commons/thumb/7/7d/2017_Ford_Fiesta_Zetec_Turbo_1.0_Front.jpg/1280px-2017_Ford_Fiesta_Zetec_Turbo_1.0_Front.jpg', // CC BY-SA 4.0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/f/fe/2017_Ford_Fiesta_Zetec_Turbo_1.0_Rear.jpg/1280px-2017_Ford_Fiesta_Zetec_Turbo_1.0_Rear.jpg', // CC BY-SA 4.0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/8/87/2014_Ford_Fiesta_Titanium_X_EcoBoost_1.0_Front.jpg/1280px-2014_Ford_Fiesta_Titanium_X_EcoBoost_1.0_Front.jpg', // CC BY-SA 4.0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/7/77/Ford_Fiesta_1.6_Titanium_2016_%2853420068137%29.jpg/1280px-Ford_Fiesta_1.6_Titanium_2016_%2853420068137%29.jpg', // CC BY-SA 2.0
        ],
        'Ford Focus' => [
            'https://upload.wikimedia.org/wikipedia/commons/thumb/4/44/2015_Ford_Focus_Titanium_1.0_Front.jpg/1280px-2015_Ford_Focus_Titanium_1.0_Front.jpg', // CC BY-SA 4.0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/9/9a/2015_Ford_Focus_Titanium_1.0_Rear.jpg/1280px-2015_Ford_Focus_Titanium_1.0_Rear.jpg', // CC BY-SA 4.0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/f/f1/2018_Ford_Focus_ST-Line_EcoBoost_1.0_%281%29.jpg/1280px-2018_Ford_Focus_ST-Line_EcoBoost_1.0_%281%29.jpg', // CC BY-SA 4.0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/5/58/Ford_Focus_IV_hatch_05_China_2019-03-25.jpg/1280px-Ford_Focus_IV_hatch_05_China_2019-03-25.jpg', // CC BY-SA 4.0
        ],
        'Ford Ranger' => [
            'https://upload.wikimedia.org/wikipedia/commons/thumb/3/35/2017_Ford_Ranger_Wildtrak_4X4_facelift_3.2_Front.jpg/1280px-2017_Ford_Ranger_Wildtrak_4X4_facelift_3.2_Front.jpg', // CC BY-SA 4.0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/5/5c/2017_Ford_Ranger_Wildtrak_4X4_facelift_3.2_Rear.jpg/1280px-2017_Ford_Ranger_Wildtrak_4X4_facelift_3.2_Rear.jpg', // CC BY-SA 4.0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/2/23/2025_Ford_Ranger_XLT_in_Argentina.jpg/1280px-2025_Ford_Ranger_XLT_in_Argentina.jpg', // CC BY-SA 4.0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/e/ec/2025_Ford_Ranger_Wildtrak_Ecoblue_4x4_Auto.jpg/1280px-2025_Ford_Ranger_Wildtrak_Ecoblue_4x4_Auto.jpg', // CC BY-SA 4.0
        ],
        'Ford EcoSport' => [
            'https://upload.wikimedia.org/wikipedia/commons/thumb/0/02/2018_Ford_EcoSport_SE_in_White_Platinum%2C_Front_Left%2C_07-17-2022.jpg/1280px-2018_Ford_EcoSport_SE_in_White_Platinum%2C_Front_Left%2C_07-17-2022.jpg', // CC BY-SA 4.0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/9/9c/2018_Ford_Ecosport_facelift_Rear.jpg/1280px-2018_Ford_Ecosport_facelift_Rear.jpg', // CC BY-SA 4.0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/4/47/Ford_Ecosport_-_Mondial_de_l%27Automobile_de_Paris_2012_-_003.jpg/1280px-Ford_Ecosport_-_Mondial_de_l%27Automobile_de_Paris_2012_-_003.jpg', // CC BY-SA 3.0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/d/de/2012_Ford_EcoSport_%28BK%29_Titanium_wagon_%282012-10-26%29_01.jpg/1280px-2012_Ford_EcoSport_%28BK%29_Titanium_wagon_%282012-10-26%29_01.jpg', // Public domain
        ],
        'Renault Sandero' => [
            'https://upload.wikimedia.org/wikipedia/commons/thumb/9/9d/2018_Renault_Sandero_Stepway_silver_front.jpg/1280px-2018_Renault_Sandero_Stepway_silver_front.jpg', // CC0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/8/8f/2022_Renault_Sandero_Stepway_rear.jpg/1280px-2022_Renault_Sandero_Stepway_rear.jpg', // CC0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/6/66/Renault_Sandero_R.S._2020_-_BugWarp_02.jpg/1280px-Renault_Sandero_R.S._2020_-_BugWarp_02.jpg', // CC BY-SA 4.0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/7/78/2017_Renault_Sandero_RS_2.0.jpg/1280px-2017_Renault_Sandero_RS_2.0.jpg', // CC BY 4.0
        ],
        'Renault Duster' => [
            'https://upload.wikimedia.org/wikipedia/commons/thumb/c/c1/Renault_Duster_%28front%29%2C_Denpasar.jpg/1280px-Renault_Duster_%28front%29%2C_Denpasar.jpg', // CC BY-SA 4.0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/9/99/Moscow%2C_Renault_Duster_silver_rear_Sept_2025_01.jpg/1280px-Moscow%2C_Renault_Duster_silver_rear_Sept_2025_01.jpg', // CC0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/6/6c/Renault_Duster_1.6_Dynamique_%282015%29_%2852720097747%29.jpg/1280px-Renault_Duster_1.6_Dynamique_%282015%29_%2852720097747%29.jpg', // CC BY 2.0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/d/d3/Renault_Duster_Techroad%2C_Natal_%28DSC05979%29.jpg/1280px-Renault_Duster_Techroad%2C_Natal_%28DSC05979%29.jpg', // CC BY-SA 4.0
        ],
        'Renault Kwid' => [
            'https://upload.wikimedia.org/wikipedia/commons/thumb/3/33/Renault_Kwid_2017_in_Montevideo_%28front%29.jpg/1280px-Renault_Kwid_2017_in_Montevideo_%28front%29.jpg', // CC BY-SA 4.0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/e/ed/Renault_Kwid_2017_in_Montevideo_%28back%29.jpg/1280px-Renault_Kwid_2017_in_Montevideo_%28back%29.jpg', // CC BY-SA 4.0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/8/85/Renault_-_Kwid_-_Kolkata_2016-01-31_9478.JPG/1280px-Renault_-_Kwid_-_Kolkata_2016-01-31_9478.JPG', // CC BY 3.0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/c/c9/Renault_Kwid%2C_Karangasem_-_10_Maret_2018_-_depan.jpg/1280px-Renault_Kwid%2C_Karangasem_-_10_Maret_2018_-_depan.jpg', // CC BY-SA 4.0
        ],
        'Renault Logan' => [
            'https://upload.wikimedia.org/wikipedia/commons/thumb/3/34/2020_Renault_Logan_Stepway_front.jpg/1280px-2020_Renault_Logan_Stepway_front.jpg', // CC0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/a/ac/2020_Renault_Logan_Stepway_rear.jpg/1280px-2020_Renault_Logan_Stepway_rear.jpg', // CC0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/9/9e/2021_Renault_Logan_Stepway_white.jpg/1280px-2021_Renault_Logan_Stepway_white.jpg', // CC0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/b/bd/Moscow%2C_Renault_Logan_II_Stepway_silver%2C_Oct_2025_02.jpg/1280px-Moscow%2C_Renault_Logan_II_Stepway_silver%2C_Oct_2025_02.jpg', // CC0
        ],
        'Peugeot 208' => [
            'https://upload.wikimedia.org/wikipedia/commons/thumb/0/03/2021_Peugeot_208_1.2_PureTech_100_Allure_%28Front%29.jpg/1280px-2021_Peugeot_208_1.2_PureTech_100_Allure_%28Front%29.jpg', // CC0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/f/f0/2021_Peugeot_208_1.2_PureTech_100_Allure_%28Rear%29.jpg/1280px-2021_Peugeot_208_1.2_PureTech_100_Allure_%28Rear%29.jpg', // CC0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/0/03/Peugeot_208_5door_facelift.jpg/1280px-Peugeot_208_5door_facelift.jpg', // CC BY-SA 4.0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/3/33/Peugeot_208_%28Freiburg_i._Br.%2C_2025%29.jpg/1280px-Peugeot_208_%28Freiburg_i._Br.%2C_2025%29.jpg', // CC BY-SA 4.0
        ],
        'Peugeot 308' => [
            'https://upload.wikimedia.org/wikipedia/commons/thumb/1/16/2018_Peugeot_308_Active_1.2_Front.jpg/1280px-2018_Peugeot_308_Active_1.2_Front.jpg', // CC BY-SA 4.0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/1/1b/2023_Peugeot_308_1.2_PureTech_GT_%28Rear%29.jpg/1280px-2023_Peugeot_308_1.2_PureTech_GT_%28Rear%29.jpg', // CC0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/5/5d/Peugeot_308_II_1.6_Allure_Hatchback_2016.jpg/1280px-Peugeot_308_II_1.6_Allure_Hatchback_2016.jpg', // CC BY-SA 4.0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/7/72/Osaka_Motor_Show_2015_%28178%29_-_Peugeot_308_GTi_270_%28ABA-T9HN02%29.JPG/1280px-Osaka_Motor_Show_2015_%28178%29_-_Peugeot_308_GTi_270_%28ABA-T9HN02%29.JPG', // CC BY-SA 4.0
        ],
        'Peugeot 2008' => [
            'https://upload.wikimedia.org/wikipedia/commons/thumb/b/b9/2017_Peugeot_2008_Allure_1.2_Front.jpg/1280px-2017_Peugeot_2008_Allure_1.2_Front.jpg', // CC BY-SA 4.0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/b/b8/2020_Peugeot_2008_1.2_PureTech_GT_Rear.jpg/1280px-2020_Peugeot_2008_1.2_PureTech_GT_Rear.jpg', // CC0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/b/b7/Geneva_MotorShow_2013_-_Peugeot_2008_orange.jpg/1280px-Geneva_MotorShow_2013_-_Peugeot_2008_orange.jpg', // CC BY-SA 3.0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/1/1b/Peugeot_2008_82_VTi_Allure_%E2%80%93_Frontansicht%2C_18._Mai_2013%2C_M%C3%BCnster.jpg/1280px-Peugeot_2008_82_VTi_Allure_%E2%80%93_Frontansicht%2C_18._Mai_2013%2C_M%C3%BCnster.jpg', // CC BY-SA 3.0 de
        ],
        'Peugeot Partner' => [
            'https://upload.wikimedia.org/wikipedia/commons/thumb/c/cc/Peugeot_Partner_front_20071205.jpg/1280px-Peugeot_Partner_front_20071205.jpg', // CC BY-SA 3.0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/4/4a/2012_Peugeot_Partner_1.6_HDI_panel_van.JPG/1280px-2012_Peugeot_Partner_1.6_HDI_panel_van.JPG', // CC0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/3/33/Peugeot_Partner_PN_des_Pyr%C3%A9n%C3%A9es_Bar%C3%A8ges_2012.jpg/1280px-Peugeot_Partner_PN_des_Pyr%C3%A9n%C3%A9es_Bar%C3%A8ges_2012.jpg', // CC BY-SA 3.0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/0/05/Peugeot_Partner_2_%282008-2012%29.JPG/1280px-Peugeot_Partner_2_%282008-2012%29.JPG', // CC BY-SA 3.0
        ],
        'Fiat Argo' => [
            'https://upload.wikimedia.org/wikipedia/commons/thumb/5/5b/Fiat_Argo_2020_Trekking_in_Uruguay_%28front%29.jpg/1280px-Fiat_Argo_2020_Trekking_in_Uruguay_%28front%29.jpg', // CC BY-SA 4.0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/1/1d/Fiat_Argo_2020_Trekking_in_Uruguay_%28rear%29.jpg/1280px-Fiat_Argo_2020_Trekking_in_Uruguay_%28rear%29.jpg', // CC BY-SA 4.0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/f/fa/Fiat_Argo_1.3_Drive_ELX_2018_%2826635198997%29.jpg/1280px-Fiat_Argo_1.3_Drive_ELX_2018_%2826635198997%29.jpg', // CC BY-SA 2.0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/8/8b/2022_Fiat_Argo_1.3_Drive_ELX.jpg/1280px-2022_Fiat_Argo_1.3_Drive_ELX.jpg', // CC BY-SA 2.0
        ],
        'Fiat Cronos' => [
            'https://upload.wikimedia.org/wikipedia/commons/thumb/4/44/2021_Fiat_Cronos_1.8_16v_Precision_%28front_view%29.jpg/1280px-2021_Fiat_Cronos_1.8_16v_Precision_%28front_view%29.jpg', // CC BY 4.0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/d/d6/2021_Fiat_Cronos_1.8_16v_Precision_%28rear_view%29.jpg/1280px-2021_Fiat_Cronos_1.8_16v_Precision_%28rear_view%29.jpg', // CC BY 4.0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/2/20/Fiat_Cronos_1.8_Precision_2019_%2845941615375%29.jpg/1280px-Fiat_Cronos_1.8_Precision_2019_%2845941615375%29.jpg', // CC BY-SA 2.0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/8/8f/Fiat_Cronos_1.3_Drive_GSE_2018_%2843902636985%29.jpg/1280px-Fiat_Cronos_1.3_Drive_GSE_2018_%2843902636985%29.jpg', // CC BY-SA 2.0
        ],
        'Fiat Toro' => [
            'https://upload.wikimedia.org/wikipedia/commons/thumb/a/a6/Fiat_Toro_2018_in_Punta_del_Este_%28front%29_01.jpg/1280px-Fiat_Toro_2018_in_Punta_del_Este_%28front%29_01.jpg', // CC BY-SA 4.0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/4/4f/Fiat_Toro_2018_in_Punta_del_Este_%28back%29.jpg/1280px-Fiat_Toro_2018_in_Punta_del_Este_%28back%29.jpg', // CC BY-SA 4.0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/9/9c/2022_Fiat_Toro_2.0_Multijet_Volcano.jpg/1280px-2022_Fiat_Toro_2.0_Multijet_Volcano.jpg', // CC BY 4.0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/3/3b/2017_Fiat_Toro_2.0_Freedom_4x2_%28front%29.jpg/1280px-2017_Fiat_Toro_2.0_Freedom_4x2_%28front%29.jpg', // CC BY 4.0
        ],
        'Fiat Palio' => [
            'https://upload.wikimedia.org/wikipedia/commons/thumb/0/01/Fiat_Palio_CN_facelift_Shishi_01_2022-10-02.jpg/1280px-Fiat_Palio_CN_facelift_Shishi_01_2022-10-02.jpg', // CC BY-SA 4.0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/4/49/Fiat_Palio_Weekend_Wagon_Rear_2017.12.22.jpg/1280px-Fiat_Palio_Weekend_Wagon_Rear_2017.12.22.jpg', // CC BY-SA 4.0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/8/8d/Fiat_Palio_China_2012-04-28.jpg/1280px-Fiat_Palio_China_2012-04-28.jpg', // CC BY-SA 3.0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/b/bc/Fiat_Palio_1.4_Attractive_2014_%2812280182114%29.jpg/1280px-Fiat_Palio_1.4_Attractive_2014_%2812280182114%29.jpg', // CC BY-SA 2.0
        ],
        'Nissan Versa' => [
            'https://upload.wikimedia.org/wikipedia/commons/thumb/7/76/2020_Nissan_Versa_SV_1.6L%2C_front_2.29.20.jpg/1280px-2020_Nissan_Versa_SV_1.6L%2C_front_2.29.20.jpg', // CC BY-SA 4.0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/c/cb/2015_Nissan_Versa_Note_SV%2C_rear_right_side.jpg/1280px-2015_Nissan_Versa_Note_SV%2C_rear_right_side.jpg', // CC BY-SA 3.0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/8/8a/2017_Nissan_Versa_Note_Red_Rental.jpg/1280px-2017_Nissan_Versa_Note_Red_Rental.jpg', // CC BY-SA 2.0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/7/74/Nissan_Versa_1.6_Sense_2013_%2824292515563%29.jpg/1280px-Nissan_Versa_1.6_Sense_2013_%2824292515563%29.jpg', // CC BY-SA 2.0
        ],
        'Nissan Kicks' => [
            'https://upload.wikimedia.org/wikipedia/commons/thumb/2/21/2018_Nissan_Kicks_SV_front_3.30.19.jpg/1280px-2018_Nissan_Kicks_SV_front_3.30.19.jpg', // CC BY-SA 4.0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/1/11/2020_Nissan_Kicks_e-Power_VL_%28Rear%29.jpg/1280px-2020_Nissan_Kicks_e-Power_VL_%28Rear%29.jpg', // CC BY-SA 4.0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/f/f4/Nissan_Kicks_e-Power_1.2_VE_2022.jpg/1280px-Nissan_Kicks_e-Power_1.2_VE_2022.jpg', // CC BY-SA 4.0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/6/60/Nissan_Kicks_e-Power_1.2_VE_2023_%281%29.jpg/1280px-Nissan_Kicks_e-Power_1.2_VE_2023_%281%29.jpg', // CC BY-SA 4.0
        ],
        'Nissan Frontier' => [
            'https://upload.wikimedia.org/wikipedia/commons/thumb/4/4e/2012_Nissan_Frontier_2.7_S_Bravado_4x2%2C_front_left%2C_06-20-2024.jpg/1280px-2012_Nissan_Frontier_2.7_S_Bravado_4x2%2C_front_left%2C_06-20-2024.jpg', // CC BY-SA 4.0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/c/cc/2012_Nissan_Frontier_2.7_S_Bravado_4x2%2C_rear_left%2C_06-20-2024.jpg/1280px-2012_Nissan_Frontier_2.7_S_Bravado_4x2%2C_rear_left%2C_06-20-2024.jpg', // CC BY-SA 4.0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/3/3e/Nissan_Frontier_%28D41%29_Pro-4X_Automesse_Ludwigsburg_2022_1X7A5885.jpg/1280px-Nissan_Frontier_%28D41%29_Pro-4X_Automesse_Ludwigsburg_2022_1X7A5885.jpg', // CC BY-SA 4.0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/0/0d/2006_Nissan_Frontier_2.7_Standard_4x2_in_Polar_White%2C_08-06-2024.jpg/1280px-2006_Nissan_Frontier_2.7_Standard_4x2_in_Polar_White%2C_08-06-2024.jpg', // CC BY-SA 4.0
        ],
        'Nissan March' => [
            'https://upload.wikimedia.org/wikipedia/commons/thumb/3/37/Nissan_March_NISMO_S_%28K13%29_front.JPG/1280px-Nissan_March_NISMO_S_%28K13%29_front.JPG', // CC0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/1/1d/%E2%80%9822_Nissan_March_%E2%80%94_Front.jpg/1280px-%E2%80%9822_Nissan_March_%E2%80%94_Front.jpg', // Public domain
            'https://upload.wikimedia.org/wikipedia/commons/thumb/7/76/NISSAN_MARCH_%28K12%29_MACAU_%282%29.jpg/1280px-NISSAN_MARCH_%28K12%29_MACAU_%282%29.jpg', // CC BY-SA 4.0
            'https://upload.wikimedia.org/wikipedia/commons/thumb/5/5a/Nissan_March_K13_--_Front.jpg/1280px-Nissan_March_K13_--_Front.jpg', // Public domain
        ],
    ];

    /**
     * URL para la foto número $posicion (0 = portada) de un vehículo.
     * Si el modelo tiene menos fotos que el vehículo, se repiten desde el principio.
     * Devuelve null si el modelo no está en la lista.
     */
    public static function urlFoto(string $marca, string $modelo, int $posicion): ?string
    {
        $urls = self::FOTOS["$marca $modelo"] ?? null;

        return $urls ? $urls[$posicion % count($urls)] : null;
    }

    /**
     * Cambia la ruta de las fotos que YA existen, según la marca y el modelo del vehículo.
     * No crea ni borra filas y no toca la columna orden.
     */
    public function run(): void
    {
        $actualizadas = 0;

        // Transacción: si una actualización falla, no queda la tabla a medias
        DB::transaction(function () use (&$actualizadas) {
            // with('fotos') trae las fotos ordenadas por 'orden' (así lo define la relación)
            foreach (Vehiculo::with('fotos')->get() as $vehiculo) {
                foreach ($vehiculo->fotos as $posicion => $foto) {
                    $url = self::urlFoto($vehiculo->marca, $vehiculo->modelo, $posicion);

                    // Vehículos cargados a mano con un modelo que no está en la lista: se dejan como están
                    if ($url) {
                        $foto->update(['ruta' => $url]);
                        $actualizadas++;
                    }
                }
            }
        });

        $this->command?->info("Fotos actualizadas: $actualizadas");
    }
}
